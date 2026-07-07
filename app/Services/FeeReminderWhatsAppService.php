<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FeePenaltyStatus;
use App\Models\FeeInstallment;
use App\Models\FeeReminderLog;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Support\SystemUser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class FeeReminderWhatsAppService
{
    public function __construct(
        protected WhatsAppCampaignService $campaigns,
        protected FeesDashboardService $fees,
    ) {}

    /**
     * @return array{queued: int, skipped: int, reason?: string}
     */
    public function maybeQueueDailyReminders(?User $staff = null): array
    {
        try {
            if (! \App\Support\FeatureGate::enabled(\App\Enums\LicenseFeature::WhatsApp)) {
                return ['queued' => 0, 'skipped' => 0, 'reason' => 'WhatsApp feature disabled'];
            }

            if (! Setting::getValue('whatsapp.fee_reminder_autosend_enabled')) {
                return ['queued' => 0, 'skipped' => 0, 'reason' => 'Fee reminders disabled'];
            }

            $settings = app(WhatsAppSettingsService::class);
            $template = $settings->resolveAutomationTemplate(
                Setting::getValue('whatsapp.fee_reminder_live_campaign_id'),
                Setting::getValue('whatsapp.fee_reminder_template_id'),
            );

            if (! $template) {
                return ['queued' => 0, 'skipped' => 0, 'reason' => 'No approved template selected'];
            }

            $staff ??= SystemUser::resolve();
            $eligible = $this->eligibleStudents();

            if ($eligible->isEmpty()) {
                return ['queued' => 0, 'skipped' => 0, 'reason' => 'No overdue students to remind'];
            }

            $studentContexts = $eligible
                ->mapWithKeys(fn (array $row): array => [(int) $row['student_id'] => $row])
                ->all();

            $campaign = $this->campaigns->createCampaign([
                'name' => 'Fee reminder · '.now()->format('d M Y'),
                'whatsapp_template_id' => $template->id,
                'student_ids' => array_keys($studentContexts),
                'campaign_variables' => [
                    'audience_source' => 'fee_reminder',
                    'date' => now()->toDateString(),
                    '_student_ids' => array_keys($studentContexts),
                    '_student_fee_context' => $studentContexts,
                ],
            ], $staff);

            $this->campaigns->queueCampaign($campaign, $staff);

            $this->logReminders($campaign, $eligible);

            return [
                'queued' => $eligible->count(),
                'skipped' => 0,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Fee reminder WhatsApp failed: '.$exception->getMessage());

            return ['queued' => 0, 'skipped' => 0, 'reason' => $exception->getMessage()];
        }
    }

    /**
     * @return Collection<int, array{
     *     student_id: int,
     *     fee_installment_id: int,
     *     pending_amount: string,
     *     due_date: string,
     *     installment_label: string,
     *     days_overdue: int,
     *     penalty_pending: string,
     * }>
     */
    public function eligibleStudents(?Carbon $asOf = null): Collection
    {
        $today = ($asOf ?? now())->copy()->startOfDay();
        $minDaysOverdue = max(0, (int) config('fees.reminder.min_days_overdue', 1));
        $cooldownDays = max(1, (int) config('fees.reminder.cooldown_days', 7));
        $cooldownCutoff = $today->copy()->subDays($cooldownDays);

        $defaulters = $this->fees->defaulters($today);

        if ($defaulters->isEmpty()) {
            return collect();
        }

        $recentlyReminded = FeeReminderLog::query()
            ->where('sent_at', '>=', $cooldownCutoff)
            ->pluck('student_id')
            ->flip();

        $installmentsByStudent = FeeInstallment::query()
            ->with(['feeStructure.penalties'])
            ->where('pending_amount', '>', 0)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today->toDateString())
            ->whereHas('feeStructure.enrollment', fn ($query) => $query
                ->where('is_active', true)
                ->where('status', EnrollmentStatus::Enrolled))
            ->get()
            ->groupBy(fn (FeeInstallment $row): int => (int) $row->feeStructure?->enrollment?->student_id);

        return $defaulters
            ->filter(function (array $row) use ($minDaysOverdue, $recentlyReminded): bool {
                if ((int) ($row['days_overdue'] ?? 0) < $minDaysOverdue) {
                    return false;
                }

                if (blank($row['mobile'] ?? null)) {
                    return false;
                }

                return ! $recentlyReminded->has((int) $row['student_id']);
            })
            ->map(function (array $row) use ($installmentsByStudent): array {
                $studentId = (int) $row['student_id'];
                $rows = $installmentsByStudent->get($studentId, collect());
                /** @var FeeInstallment|null $first */
                $first = $rows->sortBy(fn (FeeInstallment $installment): string => $installment->due_date?->toDateString() ?? '9999-12-31')->first();

                $penaltyPending = 0.0;

                if ($first?->feeStructure) {
                    $penaltyPending = round((float) $first->feeStructure->penalties
                        ->where('status', FeePenaltyStatus::Pending)
                        ->sum('penalty_amount'), 2);
                }

                return [
                    'student_id' => $studentId,
                    'fee_installment_id' => (int) ($first?->id ?? 0),
                    'pending_amount' => $this->formatAmount((float) ($row['pending_amount'] ?? 0)),
                    'due_date' => filled($row['next_due_date'] ?? null)
                        ? Carbon::parse((string) $row['next_due_date'])->format('d M Y')
                        : '',
                    'installment_label' => (string) ($first?->label ?? 'Installment'),
                    'days_overdue' => (int) ($row['days_overdue'] ?? 0),
                    'penalty_pending' => $this->formatAmount($penaltyPending),
                ];
            })
            ->filter(fn (array $row): bool => $row['fee_installment_id'] > 0)
            ->values();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $eligible
     */
    protected function logReminders(WhatsAppCampaign $campaign, Collection $eligible): void
    {
        $sentAt = now();

        foreach ($eligible as $row) {
            FeeReminderLog::query()->create([
                'student_id' => $row['student_id'],
                'fee_installment_id' => $row['fee_installment_id'],
                'whatsapp_campaign_id' => $campaign->id,
                'sent_at' => $sentAt,
            ]);
        }
    }

    protected function formatAmount(float $amount): string
    {
        return number_format(round($amount, 2), 2);
    }
}
