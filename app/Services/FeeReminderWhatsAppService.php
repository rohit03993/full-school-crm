<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeMiscChargeKind;
use App\Enums\FeeMiscChargeStatus;
use App\Enums\FeeReminderStage;
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
use Illuminate\Validation\ValidationException;

class FeeReminderWhatsAppService
{
    public const SETTING_LAST_AUTO_DATE = 'whatsapp.fee_reminder_last_auto_date';

    public function __construct(
        protected WhatsAppCampaignService $campaigns,
        protected FeesDashboardService $fees,
    ) {}

    /**
     * @return array{queued: int, skipped: int, reason?: string}
     */
    public function maybeQueueDailyReminders(?User $staff = null, bool $ignoreSendWindow = false): array
    {
        try {
            if (! \App\Support\FeatureGate::enabled(\App\Enums\LicenseFeature::WhatsApp)) {
                return ['queued' => 0, 'skipped' => 0, 'reason' => 'WhatsApp feature disabled'];
            }

            if (! Setting::getValue('whatsapp.fee_reminder_autosend_enabled')) {
                return ['queued' => 0, 'skipped' => 0, 'reason' => 'Fee reminders disabled'];
            }

            if (! $ignoreSendWindow && ! $this->isWithinDailySendWindow()) {
                return ['queued' => 0, 'skipped' => 0, 'reason' => 'Outside send window (waiting for configured send time)'];
            }

            $staff ??= SystemUser::resolve();
            $queued = 0;
            $reasons = [];
            $missingTemplate = false;

            foreach ([FeeReminderStage::Upcoming, FeeReminderStage::Due, FeeReminderStage::Overdue] as $stage) {
                if (! $this->stageEnabled($stage)) {
                    continue;
                }

                $result = $this->queueStage($stage, $staff);

                $queued += $result['queued'];

                if (str_contains((string) ($result['reason'] ?? ''), 'No approved template')) {
                    $missingTemplate = true;
                }

                if ($result['queued'] === 0 && filled($result['reason'] ?? null)) {
                    $reasons[] = $stage->value.': '.$result['reason'];
                }
            }

            if ($queued > 0 || (! $missingTemplate && ($ignoreSendWindow || $this->isWithinDailySendWindow()))) {
                Setting::setValue(self::SETTING_LAST_AUTO_DATE, now()->toDateString(), 'whatsapp');
            }

            return [
                'queued' => $queued,
                'skipped' => 0,
                'reason' => $queued > 0 ? null : (implode('; ', $reasons) ?: 'No students to remind'),
            ];
        } catch (\Throwable $exception) {
            Log::warning('Fee reminder WhatsApp failed: '.$exception->getMessage());

            return ['queued' => 0, 'skipped' => 0, 'reason' => $exception->getMessage()];
        }
    }

    public function daysBeforeDue(): int
    {
        $days = (int) Setting::getValue('whatsapp.fee_reminder_days_before', config('fees.reminder.days_before_due', 2));

        return max(1, min(14, $days));
    }

    public function sendTime(): string
    {
        $raw = trim((string) Setting::getValue('whatsapp.fee_reminder_send_time', config('fees.reminder.send_time', '10:00')));

        if (! preg_match('/^([01]?\d|2[0-3]):([0-5]\d)$/', $raw)) {
            return '10:00';
        }

        return strlen($raw) === 4 ? '0'.$raw : $raw;
    }

    public function isWithinDailySendWindow(?Carbon $now = null): bool
    {
        $now = $now ?? now();
        $last = (string) Setting::getValue(self::SETTING_LAST_AUTO_DATE, '');

        if ($last === $now->toDateString()) {
            return false;
        }

        $send = Carbon::parse($now->toDateString().' '.$this->sendTime(), $now->timezone);

        return $now->greaterThanOrEqualTo($send);
    }

    /**
     * Next pending installment for a student (any stage), or null.
     *
     * @return array{
     *     student_id: int,
     *     fee_installment_id: int,
     *     pending_amount: string,
     *     due_date: string,
     *     installment_label: string,
     *     days_overdue: int,
     *     penalty_pending: string,
     *     stage: string,
     *     mobile: string
     * }|null
     */
    public function previewForStudent(Student $student, ?Carbon $asOf = null): ?array
    {
        $today = ($asOf ?? now())->copy()->startOfDay();
        $installment = $this->nextPendingInstallment($student->id);

        if (! $installment) {
            return null;
        }

        return $this->rowFromInstallment($installment, $today, $this->stageForDueDate($installment->due_date, $today));
    }

    /**
     * @return array{queued: int, skipped: int, reason?: string, campaign_id?: int}
     */
    public function sendManual(Student $student, User $staff): array
    {
        if (! \App\Support\FeatureGate::enabled(\App\Enums\LicenseFeature::WhatsApp)) {
            throw ValidationException::withMessages(['student' => 'WhatsApp is not enabled on this licence.']);
        }

        $row = $this->previewForStudent($student);

        if (! $row) {
            throw ValidationException::withMessages(['student' => 'No pending installment with a due date.']);
        }

        if (blank($row['mobile'] ?? null)) {
            throw ValidationException::withMessages(['student' => 'Add a parent mobile number before sending.']);
        }

        $stage = FeeReminderStage::from($row['stage']);
        $template = $this->templateForStage($stage);

        if (! $template) {
            throw ValidationException::withMessages(['student' => 'Map a live WhatsApp campaign for this reminder stage under Automations.']);
        }

        $campaign = $this->campaigns->createCampaign([
            'name' => 'Fee reminder (manual) · '.$student->name.' · '.now()->format('d M Y H:i'),
            'whatsapp_template_id' => $template->id,
            'student_ids' => [$student->id],
            'campaign_variables' => [
                'audience_source' => 'fee_reminder',
                'fee_reminder_stage' => FeeReminderStage::Manual->value,
                'date' => now()->toDateString(),
                '_student_ids' => [$student->id],
                '_student_fee_context' => [$student->id => $row],
            ],
        ], $staff);

        $this->campaigns->queueCampaign($campaign, $staff);
        $this->writeLogs($campaign, collect([$row]), FeeReminderStage::Manual);

        return ['queued' => 1, 'skipped' => 0, 'campaign_id' => $campaign->id];
    }

    /**
     * @return array{queued: int, skipped: int, reason?: string}
     */
    protected function queueStage(FeeReminderStage $stage, User $staff): array
    {
        $template = $this->templateForStage($stage);

        if (! $template) {
            return ['queued' => 0, 'skipped' => 0, 'reason' => 'No approved template for '.$stage->value];
        }

        $eligible = $this->eligibleStudentsForStage($stage);

        if ($eligible->isEmpty()) {
            return ['queued' => 0, 'skipped' => 0, 'reason' => 'No students for '.$stage->value];
        }

        $studentContexts = $eligible
            ->mapWithKeys(fn (array $row): array => [(int) $row['student_id'] => $row])
            ->all();

        $campaign = $this->campaigns->createCampaign([
            'name' => 'Fee reminder · '.$stage->value.' · '.now()->format('d M Y'),
            'whatsapp_template_id' => $template->id,
            'student_ids' => array_keys($studentContexts),
            'campaign_variables' => [
                'audience_source' => 'fee_reminder',
                'fee_reminder_stage' => $stage->value,
                'date' => now()->toDateString(),
                '_student_ids' => array_keys($studentContexts),
                '_student_fee_context' => $studentContexts,
            ],
        ], $staff);

        $this->campaigns->queueCampaign($campaign, $staff);
        $this->writeLogs($campaign, $eligible, $stage);

        return ['queued' => $eligible->count(), 'skipped' => 0];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function eligibleStudents(?Carbon $asOf = null): Collection
    {
        return $this->eligibleStudentsForStage(FeeReminderStage::Overdue, $asOf);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function eligibleStudentsForStage(FeeReminderStage $stage, ?Carbon $asOf = null): Collection
    {
        $today = ($asOf ?? now())->copy()->startOfDay();
        $upcomingDate = $today->copy()->addDays($this->daysBeforeDue())->toDateString();
        $todayDate = $today->toDateString();

        $installments = FeeInstallment::query()
            ->with([
                'feeStructure.enrollment.student',
                'feeStructure.miscCharges',
            ])
            ->where('pending_amount', '>', 0)
            ->whereNotNull('due_date')
            ->whereHas('feeStructure.enrollment', fn ($q) => $q
                ->where('is_active', true)
                ->where('status', EnrollmentStatus::Enrolled))
            ->get()
            ->groupBy(fn (FeeInstallment $row): int => (int) $row->feeStructure?->enrollment?->student_id)
            ->filter(fn (Collection $rows, int $studentId): bool => $studentId > 0)
            ->map(function (Collection $rows) use ($today, $stage, $upcomingDate, $todayDate): ?array {
                /** @var FeeInstallment $first */
                $first = $rows->sortBy(fn (FeeInstallment $row): string => $row->due_date?->toDateString() ?? '9999-12-31')->first();
                $due = $first->due_date?->toDateString();

                $matches = match ($stage) {
                    FeeReminderStage::Upcoming => $due === $upcomingDate,
                    FeeReminderStage::Due => $due === $todayDate,
                    FeeReminderStage::Overdue => $due !== null && $due < $todayDate,
                    FeeReminderStage::Manual => true,
                };

                if (! $matches) {
                    return null;
                }

                return $this->rowFromInstallment($first, $today, $stage);
            })
            ->filter();

        if ($stage === FeeReminderStage::Overdue) {
            $minDays = max(0, (int) config('fees.reminder.min_days_overdue', 1));
            $cooldownDays = max(1, (int) config('fees.reminder.cooldown_days', 7));
            $cooldownCutoff = $today->copy()->subDays($cooldownDays);

            $recent = FeeReminderLog::query()
                ->where('stage', FeeReminderStage::Overdue->value)
                ->where('sent_at', '>=', $cooldownCutoff)
                ->pluck('student_id')
                ->flip();

            $installments = $installments->filter(function (array $row) use ($minDays, $recent): bool {
                if ((int) ($row['days_overdue'] ?? 0) < $minDays) {
                    return false;
                }

                return ! $recent->has((int) $row['student_id']);
            });
        } else {
            $already = FeeReminderLog::query()
                ->where('stage', $stage->value)
                ->whereIn('fee_installment_id', $installments->pluck('fee_installment_id')->all())
                ->pluck('fee_installment_id')
                ->flip();

            $installments = $installments->filter(
                fn (array $row): bool => ! $already->has((int) $row['fee_installment_id']),
            );
        }

        return $installments
            ->filter(fn (array $row): bool => (int) $row['fee_installment_id'] > 0 && filled($row['mobile'] ?? null))
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rowFromInstallment(FeeInstallment $installment, Carbon $today, FeeReminderStage $stage): array
    {
        $student = $installment->feeStructure?->enrollment?->student;
        $due = $installment->due_date;
        $daysOverdue = $due ? max(0, (int) $due->copy()->startOfDay()->diffInDays($today, false)) : 0;

        $penaltyPending = 0.0;

        if ($installment->feeStructure) {
            $penaltyPending = round((float) $installment->feeStructure->miscCharges
                ->filter(fn ($charge) => $charge->kind === FeeMiscChargeKind::LateFeePenalty
                    && $charge->status !== FeeMiscChargeStatus::Cancelled)
                ->sum(fn ($charge) => $charge->pendingAmount()), 2);
        }

        return [
            'student_id' => (int) ($student?->id ?? 0),
            'fee_installment_id' => (int) $installment->id,
            'pending_amount' => $this->formatAmount((float) $installment->pending_amount),
            'due_date' => $due ? $due->format('d M Y') : '',
            'installment_label' => (string) ($installment->label ?? 'Installment'),
            'days_overdue' => $daysOverdue,
            'penalty_pending' => $this->formatAmount($penaltyPending),
            'stage' => $stage->value,
            'mobile' => (string) ($student?->mobile ?? ''),
        ];
    }

    protected function nextPendingInstallment(int $studentId): ?FeeInstallment
    {
        return FeeInstallment::query()
            ->with(['feeStructure.enrollment.student', 'feeStructure.miscCharges'])
            ->where('pending_amount', '>', 0)
            ->whereNotNull('due_date')
            ->whereHas('feeStructure.enrollment', fn ($q) => $q
                ->where('student_id', $studentId)
                ->where('is_active', true)
                ->where('status', EnrollmentStatus::Enrolled))
            ->orderBy('due_date')
            ->orderBy('sort_order')
            ->first();
    }

    protected function stageForDueDate(?Carbon $due, Carbon $today): FeeReminderStage
    {
        if (! $due) {
            return FeeReminderStage::Overdue;
        }

        $dueDay = $due->copy()->startOfDay();

        if ($dueDay->equalTo($today)) {
            return FeeReminderStage::Due;
        }

        if ($dueDay->greaterThan($today)) {
            return FeeReminderStage::Upcoming;
        }

        return FeeReminderStage::Overdue;
    }

    protected function stageEnabled(FeeReminderStage $stage): bool
    {
        $key = match ($stage) {
            FeeReminderStage::Upcoming => 'whatsapp.fee_reminder_upcoming_enabled',
            FeeReminderStage::Due => 'whatsapp.fee_reminder_due_enabled',
            FeeReminderStage::Overdue => 'whatsapp.fee_reminder_overdue_enabled',
            FeeReminderStage::Manual => null,
        };

        if ($key === null) {
            return true;
        }

        $default = $stage === FeeReminderStage::Overdue ? '1' : '0';
        $raw = Setting::getValue($key, $default);

        return $raw === true || $raw === 1 || $raw === '1';
    }

    protected function templateForStage(FeeReminderStage $stage): ?\App\Models\WhatsAppTemplate
    {
        $settings = app(WhatsAppSettingsService::class);

        $campaignKey = match ($stage) {
            FeeReminderStage::Upcoming => 'whatsapp.fee_reminder_upcoming_live_campaign_id',
            FeeReminderStage::Due => 'whatsapp.fee_reminder_due_live_campaign_id',
            FeeReminderStage::Overdue, FeeReminderStage::Manual => 'whatsapp.fee_reminder_overdue_live_campaign_id',
        };

        $template = $settings->resolveAutomationTemplate(
            Setting::getValue($campaignKey),
            null,
        );

        if ($template) {
            return $template;
        }

        if (in_array($stage, [FeeReminderStage::Upcoming, FeeReminderStage::Due], true)) {
            return $settings->resolveAutomationTemplate(
                null,
                Setting::getValue('whatsapp.fee_reminder_template_id'),
            );
        }

        return $settings->resolveAutomationTemplate(
            Setting::getValue('whatsapp.fee_reminder_live_campaign_id'),
            Setting::getValue('whatsapp.fee_reminder_template_id'),
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $eligible
     */
    protected function writeLogs(WhatsAppCampaign $campaign, Collection $eligible, FeeReminderStage $stage): void
    {
        $sentAt = now();

        foreach ($eligible as $row) {
            FeeReminderLog::query()->create([
                'student_id' => $row['student_id'],
                'fee_installment_id' => $row['fee_installment_id'],
                'whatsapp_campaign_id' => $campaign->id,
                'stage' => $stage->value,
                'sent_at' => $sentAt,
            ]);
        }
    }

    protected function formatAmount(float $amount): string
    {
        return number_format(round($amount, 2), 2);
    }
}
