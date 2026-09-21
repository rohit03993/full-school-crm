<?php

namespace App\Services;

use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Models\ActivityAttendance;
use App\Models\ActivitySession;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppTemplate;
use App\Support\StudentExamMarksMatrix;
use App\Support\TestMarksWhatsAppTemplate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ActivityMarksWhatsAppService
{
    public function __construct(
        protected WhatsAppCampaignService $campaigns,
        protected WhatsAppSettingsService $settings,
    ) {}

    public function sessionsForMarksKey(string $marksKey): Collection
    {
        $query = ActivitySession::query();

        if (! str_contains($marksKey, '|')) {
            $query->where('metadata->test_key', $marksKey);
        } else {
            $parts = explode('|', $marksKey, 4);

            if (count($parts) !== 4) {
                return collect();
            }

            [$activityTypeId, $batchId, $date, $testLabel] = $parts;

            $query
                ->where('activity_type_id', (int) $activityTypeId)
                ->where('batch_id', (int) $batchId)
                ->whereDate('session_date', $date)
                ->where('metadata->test_name', $testLabel);
        }

        return $query->get();
    }

    /**
     * @return array<int, string> student_id => marks summary
     */
    public function buildStudentMarksSummaries(string $marksKey): array
    {
        $sessions = $this->sessionsForMarksKey($marksKey);

        if ($sessions->isEmpty()) {
            return [];
        }

        $sessionIds = $sessions->pluck('id')->all();
        $maxMarksBySession = $sessions->mapWithKeys(fn (ActivitySession $session): array => [
            $session->id => (float) ($session->metadataValue('max_marks') ?? 0),
        ]);
        $subjectBySession = $sessions->mapWithKeys(fn (ActivitySession $session): array => [
            $session->id => StudentExamMarksMatrix::subjectForSession($session),
        ]);

        $attendances = ActivityAttendance::query()
            ->where('attendable_type', ActivitySession::class)
            ->whereIn('attendable_id', $sessionIds)
            ->whereNotNull('marks_obtained')
            ->get(['student_id', 'attendable_id', 'marks_obtained']);

        /** @var array<int, list<string>> $parts */
        $parts = [];

        foreach ($attendances as $attendance) {
            $subject = $subjectBySession->get($attendance->attendable_id, 'Subject');
            $maxMarks = $maxMarksBySession->get($attendance->attendable_id);
            $mark = (float) $attendance->marks_obtained;
            $label = $maxMarks > 0
                ? "{$subject}: {$mark}/{$maxMarks}"
                : "{$subject}: {$mark}";

            $parts[(int) $attendance->student_id][] = $label;
        }

        $summaries = [];

        foreach ($parts as $studentId => $labels) {
            $summaries[$studentId] = implode(', ', $labels);
        }

        return $summaries;
    }

    /**
     * @return Collection<int, Student>
     */
    public function studentsWithMarks(string $marksKey): Collection
    {
        $summaries = $this->buildStudentMarksSummaries($marksKey);

        if ($summaries === []) {
            return collect();
        }

        return Student::query()
            ->whereIn('id', array_keys($summaries))
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->get();
    }

    /**
     * Latest class-sheet or profile send for this student and test.
     *
     * @return array{status: string, at: string, source: string, campaign_id: int}|null
     */
    public function lastSendForStudent(Student $student, string $marksKey): ?array
    {
        return $this->lastSendsForStudent($student, [$marksKey])[$marksKey] ?? null;
    }

    /**
     * @param  list<string>  $marksKeys
     * @return array<string, array{status: string, at: string, source: string, campaign_id: int}>
     */
    public function lastSendsForStudent(Student $student, array $marksKeys): array
    {
        $marksKeys = array_values(array_filter($marksKeys, fn (string $key): bool => filled($key)));

        if ($marksKeys === [] || ! Schema::hasTable('whatsapp_campaign_recipients')) {
            return [];
        }

        $recipients = WhatsAppCampaignRecipient::query()
            ->where('student_id', $student->id)
            ->whereIn('status', [
                WhatsAppRecipientStatus::Sent,
                WhatsAppRecipientStatus::Pending,
                WhatsAppRecipientStatus::Processing,
            ])
            ->whereHas('campaign', function ($query): void {
                $query
                    ->where('campaign_variables->audience_source', 'activity_marks')
                    ->where(function ($inner): void {
                        $inner->whereIn('status', [
                            WhatsAppCampaignStatus::Queued,
                            WhatsAppCampaignStatus::Running,
                            WhatsAppCampaignStatus::Paused,
                            WhatsAppCampaignStatus::Completed,
                        ])->orWhere('sent_count', '>', 0);
                    });
            })
            ->with('campaign')
            ->orderByDesc('id')
            ->get();

        $latest = [];

        foreach ($recipients as $recipient) {
            $testKey = trim((string) $recipient->campaign?->campaignVariable('test_key'));

            if ($testKey === '' || ! in_array($testKey, $marksKeys, true) || isset($latest[$testKey])) {
                continue;
            }

            $at = $recipient->updated_at
                ?? $recipient->campaign?->shot_at
                ?? $recipient->campaign?->finished_at
                ?? $recipient->created_at;

            $latest[$testKey] = [
                'status' => $recipient->status === WhatsAppRecipientStatus::Sent ? 'sent' : 'queued',
                'at' => $at?->timezone((string) config('app.timezone'))->format('d M Y, h:i A') ?? '—',
                'source' => ((int) $recipient->campaign->campaignVariable('only_student_id')) > 0
                    ? 'profile'
                    : 'class_sheet',
                'campaign_id' => (int) $recipient->whatsapp_campaign_id,
            ];
        }

        return $latest;
    }

    /**
     * Class-sheet sends for this exam (not one-student profile sends).
     *
     * @return array{
     *     eligible_now: int,
     *     has_prior_class_send: bool,
     *     button_label: string,
     *     sends: list<array{
     *         campaign_id: int,
     *         at: string,
     *         at_iso: ?string,
     *         staff_name: string,
     *         total: int,
     *         sent: int,
     *         failed: int,
     *         pending: int,
     *         result_line: string,
     *         status: string
     *     }>
     * }
     */
    public function classSheetSendHistory(string $marksKey): array
    {
        $eligibleNow = $this->studentsWithMarks($marksKey)->count();
        $queueLabel = 'Queue WhatsApp to all students with marks';
        $resendLabel = 'Resend WhatsApp to all students with marks';

        if (blank($marksKey) || ! Schema::hasTable('whatsapp_campaigns')) {
            return [
                'eligible_now' => $eligibleNow,
                'has_prior_class_send' => false,
                'button_label' => $queueLabel,
                'sends' => [],
            ];
        }

        $campaigns = WhatsAppCampaign::query()
            ->with(['createdBy', 'shotBy'])
            ->where('campaign_variables->audience_source', 'activity_marks')
            ->where('campaign_variables->test_key', $marksKey)
            ->where(function ($query): void {
                $query
                    ->whereIn('status', [
                        WhatsAppCampaignStatus::Queued,
                        WhatsAppCampaignStatus::Running,
                        WhatsAppCampaignStatus::Paused,
                        WhatsAppCampaignStatus::Completed,
                    ])
                    ->orWhere('sent_count', '>', 0);
            })
            ->orderByDesc('id')
            ->get()
            ->filter(fn (WhatsAppCampaign $campaign): bool => (int) $campaign->campaignVariable('only_student_id') === 0)
            ->values();

        $sends = $campaigns->map(function (WhatsAppCampaign $campaign): array {
            $total = (int) $campaign->total_recipients;
            $sent = (int) $campaign->sent_count;
            $failed = (int) $campaign->failed_count;
            $pending = max(0, $total - $sent - $failed);
            $at = $campaign->shot_at
                ?? $campaign->finished_at
                ?? $campaign->created_at;

            $staffName = $campaign->shotBy?->name
                ?? $campaign->createdBy?->name
                ?? 'Staff';

            return [
                'campaign_id' => (int) $campaign->id,
                'at' => $at?->timezone((string) config('app.timezone'))->format('d M Y, h:i A') ?? '—',
                'at_iso' => $at?->toIso8601String(),
                'staff_name' => (string) $staffName,
                'total' => $total,
                'sent' => $sent,
                'failed' => $failed,
                'pending' => $pending,
                'result_line' => $this->classSendResultLine($sent, $failed, $pending),
                'status' => $campaign->status instanceof WhatsAppCampaignStatus
                    ? $campaign->status->value
                    : (string) $campaign->status,
            ];
        })->all();

        $hasPrior = $sends !== [];

        return [
            'eligible_now' => $eligibleNow,
            'has_prior_class_send' => $hasPrior,
            'button_label' => $hasPrior ? $resendLabel : $queueLabel,
            'sends' => $sends,
        ];
    }

    protected function classSendResultLine(int $sent, int $failed, int $pending): string
    {
        $parts = [$sent.' delivered', $failed.' failed'];

        if ($pending > 0) {
            $parts[] = $pending.' still in queue';
        }

        return implode(' · ', $parts);
    }

    /**
     * @return array{mobile: string, test_name: string, test_date: string, marks_summary: string, roll: string, template_name: ?string, prior_send: array{status: string, at: string, source: string, campaign_id: int}|null}|null
     */
    public function previewForStudent(Student $student, string $marksKey): ?array
    {
        $summaries = $this->buildStudentMarksSummaries($marksKey);
        $summary = $summaries[$student->id] ?? null;

        if (! filled($summary)) {
            return null;
        }

        $session = $this->sessionsForMarksKey($marksKey)->first();

        if (! $session) {
            return null;
        }

        $student->loadMissing('activeEnrollment');

        return [
            'mobile' => (string) ($student->mobile ?? ''),
            'test_name' => StudentExamMarksMatrix::testLabelForSession($session),
            'test_date' => $session->session_date?->format('d M Y') ?? '—',
            'marks_summary' => (string) $summary,
            'roll' => (string) ($student->activeEnrollment?->enrollment_number ?? ''),
            'template_name' => $this->defaultTemplateName(),
            'prior_send' => $this->lastSendForStudent($student, $marksKey),
        ];
    }

    /**
     * @return array{heading: string, submit: string, description: string}
     */
    public function confirmCopyForStudent(Student $student, string $marksKey): array
    {
        $preview = $this->previewForStudent($student, $marksKey);

        if (! $preview) {
            return [
                'heading' => 'Send this test on WhatsApp?',
                'submit' => 'Send now',
                'description' => 'This student has no marks for this test. Nothing will be sent.',
            ];
        }

        if (blank($preview['mobile'])) {
            return [
                'heading' => 'Send this test on WhatsApp?',
                'submit' => 'Send now',
                'description' => 'Add a parent mobile number before sending.',
            ];
        }

        if (blank($preview['template_name'])) {
            return [
                'heading' => 'Send this test on WhatsApp?',
                'submit' => 'Send now',
                'description' => 'Pick test_marks on WhatsApp → Automations → Exam marks, then try again.',
            ];
        }

        $prior = $preview['prior_send'] ?? null;
        $priorStatus = is_array($prior) ? (string) ($prior['status'] ?? '') : '';
        $source = is_array($prior) ? (string) ($prior['source'] ?? '') : '';
        $sourcePhrase = $source === 'profile'
            ? "from this student's profile"
            : 'from the class mark sheet (Excel / bulk send)';

        $heading = match ($priorStatus) {
            'sent' => 'Already sent — send again?',
            'queued' => 'Already queued — send another?',
            default => 'Send this test on WhatsApp?',
        };
        $submit = $priorStatus === 'sent' ? 'Resend now' : 'Send now';
        $warning = match ($priorStatus) {
            'sent' => "This parent already received this test on WhatsApp on {$prior['at']} {$sourcePhrase}.\n\nSend again with the current marks?\n\n",
            'queued' => "A WhatsApp for this test is already queued for this parent ({$prior['at']} {$sourcePhrase}). Send another anyway?\n\n",
            default => '',
        };

        $roll = filled($preview['roll']) ? $preview['roll'] : 'no roll number';

        return [
            'heading' => $heading,
            'submit' => $submit,
            'description' => $warning
                ."To: {$preview['mobile']}\nRoll: {$roll}\nTest: {$preview['test_name']} ({$preview['test_date']})\nMarks: {$preview['marks_summary']}\nTemplate: {$preview['template_name']}\n\nOnly this student is messaged.",
        ];
    }

    public function createMarksCampaign(
        User $creator,
        WhatsAppTemplate $template,
        string $marksKey,
        string $testName,
        string $sessionDate,
        ?int $onlyStudentId = null,
    ): WhatsAppCampaign {
        $summaries = $this->buildStudentMarksSummaries($marksKey);

        if ($onlyStudentId !== null) {
            $summary = $summaries[$onlyStudentId] ?? null;

            if (! filled($summary)) {
                throw new \InvalidArgumentException('This student has no marks for this test.');
            }

            $summaries = [$onlyStudentId => $summary];
        }

        $studentIds = array_keys($summaries);

        if ($studentIds === []) {
            throw new \InvalidArgumentException('No marks were found for this test.');
        }

        $rollNumbers = \App\Models\Enrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('is_active', true)
            ->whereNotNull('enrollment_number')
            ->where('enrollment_number', '!=', '')
            ->pluck('enrollment_number', 'student_id')
            ->all();

        $campaignName = 'Marks · '.$testName;

        if ($onlyStudentId !== null) {
            $studentName = Student::query()->whereKey($onlyStudentId)->value('name');
            $campaignName .= ' · '.(filled($studentName) ? $studentName : '#'.$onlyStudentId);
        }

        return $this->campaigns->createCampaign([
            'name' => $campaignName,
            'whatsapp_template_id' => $template->id,
            'student_ids' => $studentIds,
            'campaign_variables' => array_filter([
                'audience_source' => 'activity_marks',
                'test_key' => $marksKey,
                'test_name' => $testName,
                'test_date' => $sessionDate,
                'only_student_id' => $onlyStudentId,
                '_student_ids' => $studentIds,
                '_student_marks' => $summaries,
                '_student_rolls' => $rollNumbers,
            ], fn (mixed $value): bool => $value !== null),
        ], $creator);
    }

    public function queueMarksCampaign(
        User $creator,
        int $templateId,
        string $marksKey,
        string $testName,
        string $sessionDate,
        ?int $onlyStudentId = null,
    ): WhatsAppCampaign {
        if (! \App\Support\FeatureGate::enabled(\App\Enums\LicenseFeature::WhatsApp)) {
            throw new \RuntimeException('WhatsApp module is not enabled.');
        }

        $template = WhatsAppTemplate::query()
            ->whereKey($templateId)
            ->where('is_active', true)
            ->firstOrFail()
            ->ensureParamMappings();

        $campaign = $this->createMarksCampaign(
            $creator,
            $template,
            $marksKey,
            $testName,
            $sessionDate,
            $onlyStudentId,
        );

        return $this->campaigns->queueCampaign($campaign, $creator, wait: false);
    }

    public function defaultTemplate(): ?WhatsAppTemplate
    {
        $fromAutomation = $this->settings->resolveAutomationTemplate(
            (string) Setting::getValue('whatsapp.activity_marks_live_campaign_id', ''),
        );

        if ($fromAutomation) {
            return $fromAutomation;
        }

        $preferred = array_values(array_unique(array_filter([
            (string) Setting::getValue('whatsapp.activity_marks_template_name', ''),
            ...TestMarksWhatsAppTemplate::ALIASES,
        ])));

        foreach ($preferred as $name) {
            $match = WhatsAppTemplate::query()
                ->where('name', $name)
                ->where('is_active', true)
                ->first();

            if ($match) {
                return $match;
            }
        }

        return WhatsAppTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->first(fn (WhatsAppTemplate $template): bool => TestMarksWhatsAppTemplate::looksLikeName((string) $template->name));
    }

    public function defaultTemplateName(): ?string
    {
        $name = $this->defaultTemplate()?->name;

        return filled($name) ? (string) $name : null;
    }

    public function resolveTemplateId(?int $explicitTemplateId = null): ?int
    {
        if ($explicitTemplateId !== null && $explicitTemplateId > 0) {
            $explicit = WhatsAppTemplate::query()
                ->whereKey($explicitTemplateId)
                ->where('is_active', true)
                ->first();

            if ($explicit) {
                return $explicit->id;
            }
        }

        return $this->defaultTemplate()?->id;
    }
}
