<?php

namespace App\Services;

use App\Enums\WhatsAppAudienceType;
use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Jobs\RunWhatsAppCampaignJob;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppTemplate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class WhatsAppCampaignService
{
    public function __construct(
        protected PalDigitalWhatsAppService $whatsapp,
        protected WhatsAppTemplateParamResolver $paramResolver,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     whatsapp_template_id: int,
     *     audience_type?: string,
     *     course_id?: int|null,
     *     batch_id?: int|null,
     *     academic_session_id?: int|null,
     *     visit_status_filter?: string|null,
     *     campaign_variables?: array<string, string|null>|null,
     *     student_ids?: list<int>|null
     * }  $data
     */
    public function createCampaign(array $data, User $creator): WhatsAppCampaign
    {
        $template = WhatsAppTemplate::query()->findOrFail($data['whatsapp_template_id']);
        $audienceType = WhatsAppAudienceType::tryFrom((string) ($data['audience_type'] ?? ''))
            ?? WhatsAppAudienceType::Batch;
        $students = $this->audienceStudents($data, $audienceType);

        $campaign = WhatsAppCampaign::query()->create([
            'whatsapp_template_id' => $template->id,
            'course_id' => $data['course_id'] ?? null,
            'audience_type' => $audienceType,
            'batch_id' => $data['batch_id'] ?? null,
            'academic_session_id' => $data['academic_session_id'] ?? null,
            'name' => $data['name'],
            'status' => WhatsAppCampaignStatus::Draft,
            'visit_status_filter' => $data['visit_status_filter'] ?? null,
            'campaign_variables' => $this->normalizeCampaignVariables($data['campaign_variables'] ?? null),
            'total_recipients' => $students->count(),
            'created_by' => $creator->id,
        ]);

        foreach ($students as $student) {
            WhatsAppCampaignRecipient::query()->create([
                'whatsapp_campaign_id' => $campaign->id,
                'student_id' => $student->id,
                'phone' => (string) $student->mobile,
                'status' => WhatsAppRecipientStatus::Pending,
            ]);
        }

        return $campaign->loadCount(['recipients']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function estimateAudienceCount(array $data): int
    {
        $audienceType = WhatsAppAudienceType::tryFrom((string) ($data['audience_type'] ?? ''))
            ?? WhatsAppAudienceType::Batch;

        return $this->audienceStudents($data, $audienceType)->count();
    }

    public function queueCampaign(WhatsAppCampaign $campaign, User $sender): WhatsAppCampaign
    {
        $this->refreshCampaignRecipients($campaign);

        $campaign->update([
            'status' => WhatsAppCampaignStatus::Queued,
            'shot_by' => $sender->id,
            'shot_at' => now(),
        ]);

        RunWhatsAppCampaignJob::dispatch($campaign->id);

        return $campaign->fresh();
    }

    public function sendSingle(Student $student, WhatsAppTemplate $template, User $sender): WhatsAppCampaignRecipient
    {
        $campaign = WhatsAppCampaign::query()->create([
            'whatsapp_template_id' => $template->id,
            'name' => $template->name.' · '.$student->name,
            'status' => WhatsAppCampaignStatus::Queued,
            'total_recipients' => 1,
            'created_by' => $sender->id,
            'shot_by' => $sender->id,
            'shot_at' => now(),
        ]);

        $recipient = WhatsAppCampaignRecipient::query()->create([
            'whatsapp_campaign_id' => $campaign->id,
            'student_id' => $student->id,
            'phone' => (string) $student->mobile,
            'status' => WhatsAppRecipientStatus::Pending,
        ]);

        RunWhatsAppCampaignJob::dispatch($campaign->id);

        return $recipient;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, Student>
     */
    protected function audienceStudents(array $data, WhatsAppAudienceType $audienceType): Collection
    {
        if (! empty($data['student_ids'])) {
            return Student::query()
                ->whereIn('id', $data['student_ids'])
                ->whereNotNull('mobile')
                ->where('mobile', '!=', '')
                ->get();
        }

        return match ($audienceType) {
            WhatsAppAudienceType::Batch => $this->batchAudienceStudents($data),
            WhatsAppAudienceType::Course => $this->courseAudienceStudents($data),
            WhatsAppAudienceType::Leads => $this->leadAudienceStudents($data),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, Student>
     */
    protected function batchAudienceStudents(array $data): Collection
    {
        if (empty($data['batch_id'])) {
            return collect();
        }

        return Student::query()
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->whereHas('batchStudents', function (Builder $batchStudentQuery) use ($data): void {
                $batchStudentQuery
                    ->where('is_active', true)
                    ->where('batch_id', $data['batch_id']);
            })
            ->distinct()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, Student>
     */
    protected function courseAudienceStudents(array $data): Collection
    {
        if (empty($data['course_id'])) {
            return collect();
        }

        return Student::query()
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->whereHas('enrollments', function (Builder $enrollmentQuery) use ($data): void {
                $enrollmentQuery
                    ->where('is_active', true)
                    ->where('course_id', $data['course_id']);

                if (! empty($data['academic_session_id'])) {
                    $enrollmentQuery->where('academic_session_id', $data['academic_session_id']);
                }
            })
            ->distinct()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, Student>
     */
    protected function leadAudienceStudents(array $data): Collection
    {
        $query = Student::query()
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->whereDoesntHave('enrollments', fn (Builder $enrollmentQuery): Builder => $enrollmentQuery->where('is_active', true))
            ->whereHas('enquiries', function (Builder $enquiryQuery) use ($data): void {
                if (! empty($data['course_id'])) {
                    $enquiryQuery->where('course_id', $data['course_id']);
                }

                if (! empty($data['visit_status_filter'])) {
                    $enquiryQuery->where('latest_visit_status', $data['visit_status_filter']);
                }

                $enquiryQuery->whereDoesntHave('admission', fn (Builder $admissionQuery): Builder => $admissionQuery->withTrashed());
            });

        return $query->distinct()->get();
    }

    public function refreshCampaignRecipients(WhatsAppCampaign $campaign): void
    {
        if ($campaign->status !== WhatsAppCampaignStatus::Draft) {
            return;
        }

        if ($campaign->campaignVariable('audience_source') === 'activity_marks') {
            $this->refreshActivityMarksRecipients($campaign);

            return;
        }

        if ($campaign->campaignVariable('audience_source') === 'attendance') {
            $this->refreshAttendanceRecipients($campaign);

            return;
        }

        $storedStudentIds = $campaign->campaignVariable('_student_ids');

        if (is_array($storedStudentIds) && $storedStudentIds !== []) {
            $students = Student::query()
                ->whereIn('id', $storedStudentIds)
                ->whereNotNull('mobile')
                ->where('mobile', '!=', '')
                ->get();

            $this->replaceCampaignRecipients($campaign, $students);

            return;
        }

        $data = [
            'course_id' => $campaign->course_id,
            'batch_id' => $campaign->batch_id,
            'academic_session_id' => $campaign->academic_session_id,
            'visit_status_filter' => $campaign->visit_status_filter,
        ];

        $students = $this->audienceStudents($data, $campaign->audience_type);

        $this->replaceCampaignRecipients($campaign, $students);
    }

    protected function refreshActivityMarksRecipients(WhatsAppCampaign $campaign): void
    {
        $testKey = (string) $campaign->campaignVariable('test_key');

        if ($testKey === '') {
            return;
        }

        $marksService = app(ActivityMarksWhatsAppService::class);
        $summaries = $marksService->buildStudentMarksSummaries($testKey);
        $students = $marksService->studentsWithMarks($testKey);

        $campaign->update([
            'campaign_variables' => array_merge($campaign->campaign_variables ?? [], [
                '_student_ids' => $students->pluck('id')->all(),
                '_student_marks' => $summaries,
                '_student_rolls' => $students->load('activeEnrollment')
                    ->mapWithKeys(fn (Student $student): array => [
                        $student->id => (string) ($student->activeEnrollment?->enrollment_number ?? ''),
                    ])
                    ->filter(fn (string $roll): bool => $roll !== '')
                    ->all(),
            ]),
            'total_recipients' => $students->count(),
        ]);

        $this->replaceCampaignRecipients($campaign, $students);
    }

    protected function refreshAttendanceRecipients(WhatsAppCampaign $campaign): void
    {
        $storedStudentIds = $campaign->campaignVariable('_student_ids');

        if (! is_array($storedStudentIds) || $storedStudentIds === []) {
            return;
        }

        $students = Student::query()
            ->whereIn('id', $storedStudentIds)
            ->whereNotNull('mobile')
            ->where('mobile', '!=', '')
            ->get();

        $statusByStudent = $campaign->campaignVariable('_student_attendance_status', []);

        if (! is_array($statusByStudent)) {
            $statusByStudent = [];
        }

        $eligibleStatuses = $students->mapWithKeys(
            fn (Student $student): array => [$student->id => (string) ($statusByStudent[$student->id] ?? '')]
        )->all();

        $campaign->update([
            'campaign_variables' => array_merge($campaign->campaign_variables ?? [], [
                '_student_ids' => $students->pluck('id')->all(),
                '_student_attendance_status' => $eligibleStatuses,
            ]),
            'total_recipients' => $students->count(),
        ]);

        $this->replaceCampaignRecipients($campaign, $students);
    }

    /**
     * @param  Collection<int, Student>  $students
     */
    protected function replaceCampaignRecipients(WhatsAppCampaign $campaign, Collection $students): void
    {
        $campaign->recipients()->delete();

        foreach ($students as $student) {
            WhatsAppCampaignRecipient::query()->create([
                'whatsapp_campaign_id' => $campaign->id,
                'student_id' => $student->id,
                'phone' => (string) $student->mobile,
                'status' => WhatsAppRecipientStatus::Pending,
            ]);
        }

        $campaign->update(['total_recipients' => $students->count()]);
    }

    /**
     * @param  array<string, mixed>|null  $variables
     * @return array<string, mixed>|null
     */
    protected function normalizeCampaignVariables(?array $variables): ?array
    {
        if ($variables === null) {
            return null;
        }

        $normalized = [];
        $preserveArrayKeys = ['_student_marks', '_student_ids', '_student_rolls', '_student_attendance_status', '_manual'];

        foreach ($variables as $key => $value) {
            if (is_array($value)) {
                if (in_array($key, $preserveArrayKeys, true)) {
                    $normalized[$key] = $value;

                    continue;
                }

                $nested = collect($value)
                    ->map(fn (mixed $item): string => trim((string) $item))
                    ->filter(fn (string $item): bool => $item !== '')
                    ->values()
                    ->all();

                if ($nested !== []) {
                    $normalized[$key] = $nested;
                }

                continue;
            }

            $string = trim((string) ($value ?? ''));

            if ($string !== '') {
                $normalized[$key] = $string;
            }
        }

        return $normalized === [] ? null : $normalized;
    }
}
