<?php

namespace App\Services;

use App\Enums\LicenseFeature;
use App\Enums\ParentFeeNoticeStatus;
use App\Enums\WhatsAppAudienceType;
use App\Models\Batch;
use App\Models\ParentFeeNotice;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use App\Support\FeatureGate;
use App\Support\FeeReminderWhatsAppTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ParentFeeNoticeService
{
    public function __construct(
        protected WhatsAppCampaignService $campaigns,
        protected WhatsAppTemplateCatalog $templates,
    ) {}

    /**
     * Active students in a batch (with or without mobile) for the editable grid.
     *
     * @return list<array{
     *     student_id: int,
     *     name: string,
     *     roll: string,
     *     mobile: string|null,
     *     has_mobile: bool,
     *     include: bool,
     *     amount: string,
     *     due_date: string
     * }>
     */
    public function rosterForBatch(Batch $batch): array
    {
        $students = Student::query()
            ->whereHas('batchStudents', function ($query) use ($batch): void {
                $query->where('batch_id', $batch->id)->where('is_active', true);
            })
            ->with('activeEnrollment')
            ->orderBy('name')
            ->get();

        return $students->map(function (Student $student): array {
            $mobile = filled($student->mobile) ? (string) $student->mobile : null;

            return [
                'student_id' => $student->id,
                'name' => $student->name,
                'roll' => (string) ($student->activeEnrollment?->enrollment_number ?? '—'),
                'mobile' => $mobile,
                'has_mobile' => $mobile !== null,
                'include' => $mobile !== null,
                'amount' => '',
                'due_date' => '',
            ];
        })->values()->all();
    }

    /**
     * @param  list<array{student_id: int|string, include?: bool|string|int, amount?: mixed, due_date?: mixed}>  $rows
     * @return array{queued: int, campaign_id: int, campaign: WhatsAppCampaign}
     */
    public function send(
        Batch $batch,
        WhatsAppTemplate $template,
        array $rows,
        User $staff,
    ): array {
        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            throw ValidationException::withMessages([
                'whatsapp' => 'WhatsApp is not enabled on this licence.',
            ]);
        }

        $template = WhatsAppTemplate::query()
            ->whereKey($template->id)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            throw ValidationException::withMessages([
                'template' => 'Choose an active WhatsApp template.',
            ]);
        }

        $selected = $this->validatedRows($rows);

        if ($selected->isEmpty()) {
            throw ValidationException::withMessages([
                'rows' => 'Select at least one student with amount and due date.',
            ]);
        }

        $studentIds = $selected->pluck('student_id')->all();
        $contexts = $selected
            ->mapWithKeys(fn (array $row): array => [
                $row['student_id'] => [
                    'pending_amount' => $row['amount_formatted'],
                    'due_date' => $row['due_date_formatted'],
                    'amount' => $row['amount_formatted'],
                ],
            ])
            ->all();

        $campaign = $this->campaigns->createCampaign([
            'name' => 'Parent fee notice · '.$batch->name.' · '.now()->format('d M Y H:i'),
            'whatsapp_template_id' => $template->id,
            'audience_type' => WhatsAppAudienceType::Batch->value,
            'batch_id' => $batch->id,
            'student_ids' => $studentIds,
            'campaign_variables' => [
                'audience_source' => 'parent_fee_notice',
                'date' => now()->toDateString(),
                '_student_ids' => $studentIds,
                '_manual_fee_notice_context' => $contexts,
                '_student_fee_context' => $contexts,
            ],
        ], $staff);

        $this->campaigns->queueCampaign($campaign, $staff);
        $campaign->load('recipients');

        $now = now();

        foreach ($selected as $row) {
            $recipient = $campaign->recipients->firstWhere('student_id', $row['student_id']);

            ParentFeeNotice::query()->create([
                'student_id' => $row['student_id'],
                'batch_id' => $batch->id,
                'amount' => $row['amount'],
                'due_date' => $row['due_date'],
                'whatsapp_campaign_id' => $campaign->id,
                'whatsapp_campaign_recipient_id' => $recipient?->id,
                'sent_by_user_id' => $staff->id,
                'sent_at' => $now,
                'status' => ParentFeeNoticeStatus::Queued,
            ]);
        }

        return [
            'queued' => $selected->count(),
            'campaign_id' => $campaign->id,
            'campaign' => $campaign,
        ];
    }

    /**
     * @return Collection<int, ParentFeeNotice>
     */
    public function noticesForStudent(Student $student, int $limit = 50): Collection
    {
        return ParentFeeNotice::query()
            ->with(['sentBy', 'batch', 'whatsappCampaignRecipient'])
            ->where('student_id', $student->id)
            ->orderByDesc('sent_at')
            ->limit($limit)
            ->get()
            ->each(function (ParentFeeNotice $notice): void {
                $this->syncStatusFromRecipient($notice);
            });
    }

    public function syncStatusFromRecipient(ParentFeeNotice $notice): void
    {
        $recipientStatus = $notice->whatsappCampaignRecipient?->status?->value;

        if ($recipientStatus === null) {
            return;
        }

        $mapped = match ($recipientStatus) {
            'sent' => ParentFeeNoticeStatus::Sent,
            'failed' => ParentFeeNoticeStatus::Failed,
            default => ParentFeeNoticeStatus::Queued,
        };

        if ($notice->status !== $mapped) {
            $notice->update(['status' => $mapped]);
            $notice->status = $mapped;
        }
    }

    /**
     * Preview filled body for the first selected student with amount + due date.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{
     *     ready: bool,
     *     warning: ?string,
     *     template_name: ?string,
     *     student_name: ?string,
     *     mobile: ?string,
     *     body: ?string,
     *     param_count: int,
     *     selected_count: int
     * }
     */
    public function preview(array $rows, ?int $templateId): array
    {
        $selectedCount = collect($rows)
            ->filter(fn (array $row): bool => filter_var($row['include'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->count();

        $empty = [
            'ready' => false,
            'warning' => null,
            'template_name' => null,
            'student_name' => null,
            'mobile' => null,
            'body' => null,
            'param_count' => 0,
            'selected_count' => $selectedCount,
        ];

        if (! $templateId) {
            return [...$empty, 'warning' => 'Select a WhatsApp template to preview.'];
        }

        $template = WhatsAppTemplate::query()
            ->whereKey($templateId)
            ->where('is_active', true)
            ->first();

        if (! $template) {
            return [...$empty, 'warning' => 'Selected template was not found.'];
        }

        $paramCount = (int) $template->param_count;
        $base = [
            ...$empty,
            'template_name' => $template->name,
            'param_count' => $paramCount,
        ];

        if ($paramCount < 1) {
            return [
                ...$base,
                'warning' => 'This template has 0 variables (e.g. test1). Use an approved fee_reminder template with institute, student, amount, and due date.',
                'body' => filled($template->body) ? (string) $template->body : null,
            ];
        }

        $row = collect($rows)->first(function (array $row): bool {
            if (! filter_var($row['include'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                return false;
            }

            return filled($row['amount'] ?? null) && filled($row['due_date'] ?? null) && ($row['has_mobile'] ?? false);
        });

        if (! $row) {
            return [
                ...$base,
                'warning' => 'Select students and fill amount + due date, then preview uses the first complete row.',
            ];
        }

        try {
            $due = Carbon::parse((string) $row['due_date']);
            $amount = round((float) str_replace(',', '', (string) $row['amount']), 2);
        } catch (\Throwable) {
            return [...$base, 'warning' => 'Fix amount / due date on the selected rows to preview.'];
        }

        $student = Student::query()->find((int) $row['student_id']);

        if (! $student) {
            return [...$base, 'warning' => 'Student not found for preview.'];
        }

        $amountFormatted = number_format($amount, 2, '.', ',');
        $dueFormatted = $due->format('d M Y');
        $contexts = [
            $student->id => [
                'pending_amount' => $amountFormatted,
                'due_date' => $dueFormatted,
                'amount' => $amountFormatted,
            ],
        ];

        $campaign = new WhatsAppCampaign([
            'campaign_variables' => [
                '_manual_fee_notice_context' => $contexts,
                '_student_fee_context' => $contexts,
            ],
        ]);

        $params = app(WhatsAppTemplateParamResolver::class)->resolveAll(
            $template->paramSources(),
            $student,
            null,
            null,
            $campaign,
        );

        $body = (string) ($template->body ?: FeeReminderWhatsAppTemplate::BODY_OVERDUE);

        foreach ($params as $index => $value) {
            $body = str_replace('{{'.($index + 1).'}}', (string) $value, $body);
        }

        return [
            'ready' => true,
            'warning' => FeeReminderWhatsAppTemplate::looksLikeName((string) $template->name)
                ? null
                : 'Tip: prefer fee_reminder / fee_reminder_overdue so amount and due date map correctly.',
            'template_name' => $template->name,
            'student_name' => $student->name,
            'mobile' => $student->mobile,
            'body' => $body,
            'param_count' => $paramCount,
            'selected_count' => $selectedCount,
        ];
    }

    /**
     * Prefer fee-reminder-style templates when listing options.
     *
     * @return array<int, string>
     */
    public function templateOptions(): array
    {
        $all = $this->templates->selectableTemplateOptions();

        $preferred = collect($all)
            ->filter(function (string $label, int $id): bool {
                $name = strtolower(explode(' (', $label)[0] ?? '');

                return str_contains($name, 'fee_reminder')
                    || str_contains($name, 'fee_due')
                    || str_contains($name, 'pending');
            })
            ->all();

        return $preferred !== [] ? $preferred + $all : $all;
    }

    /**
     * @param  list<array{student_id: int|string, include?: bool|string|int, amount?: mixed, due_date?: mixed}>  $rows
     * @return Collection<int, array{student_id: int, amount: float, amount_formatted: string, due_date: string, due_date_formatted: string}>
     */
    protected function validatedRows(array $rows): Collection
    {
        $selected = collect();

        foreach ($rows as $index => $row) {
            $include = filter_var($row['include'] ?? false, FILTER_VALIDATE_BOOLEAN);

            if (! $include) {
                continue;
            }

            $studentId = (int) ($row['student_id'] ?? 0);
            $amountRaw = trim((string) ($row['amount'] ?? ''));
            $dueRaw = trim((string) ($row['due_date'] ?? ''));

            if ($studentId < 1) {
                throw ValidationException::withMessages([
                    "rows.{$index}.student_id" => 'Invalid student.',
                ]);
            }

            $student = Student::query()->find($studentId);

            if (! $student || blank($student->mobile)) {
                throw ValidationException::withMessages([
                    "rows.{$index}.mobile" => ($student?->name ?? 'Student').' needs a parent mobile number.',
                ]);
            }

            if ($amountRaw === '' || ! is_numeric(str_replace(',', '', $amountRaw))) {
                throw ValidationException::withMessages([
                    "rows.{$index}.amount" => 'Enter a pending amount for '.$student->name.'.',
                ]);
            }

            $amount = round((float) str_replace(',', '', $amountRaw), 2);

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    "rows.{$index}.amount" => 'Amount must be greater than zero for '.$student->name.'.',
                ]);
            }

            if ($dueRaw === '') {
                throw ValidationException::withMessages([
                    "rows.{$index}.due_date" => 'Enter a due date for '.$student->name.'.',
                ]);
            }

            try {
                $due = Carbon::parse($dueRaw)->startOfDay();
            } catch (\Throwable) {
                throw ValidationException::withMessages([
                    "rows.{$index}.due_date" => 'Invalid due date for '.$student->name.'.',
                ]);
            }

            $selected->push([
                'student_id' => $studentId,
                'amount' => $amount,
                'amount_formatted' => number_format($amount, 2, '.', ','),
                'due_date' => $due->toDateString(),
                'due_date_formatted' => $due->format('d M Y'),
            ]);
        }

        return $selected->values();
    }
}
