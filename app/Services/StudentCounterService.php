<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\ActivityType;
use App\Enums\LeadSource;
use App\Support\MeetingForOptions;
use App\Enums\ProfilePhase;
use App\Enums\StudentStatus;
use App\Models\Admission;
use App\Models\Batch;
use App\Models\Enquiry;
use App\Models\Student;
use App\Services\AttendanceService;
use Illuminate\Support\Collection;

class StudentCounterService
{
    public function __construct(
        protected AttendanceService $attendance,
        protected ActivityAttendanceService $activityAttendance,
    ) {}
    /**
     * @return array{
     *     phase: ProfilePhase,
     *     items: array<int, array{label: string, value: int|float|string|null}>,
     *     recent_visits: Collection<int, \App\Models\Visit>,
     *     lead_sources: array{
     *         website_count: int,
     *         walk_in_count: int,
     *         meeting_for_counts: array<string, int>,
     *         first_source: ?LeadSource,
     *         latest_source: ?LeadSource,
     *         latest_meeting_for: ?MeetingFor,
     *         latest_intent: ?string,
     *         headline: string,
     *         detail: ?string
     *     },
     *     dossier: ?array{
     *         photo: ?\App\Models\Document,
     *         enrollment: \App\Models\Enrollment,
     *         admission: ?\App\Models\Admission,
     *         fees: ?\App\Models\FeeStructure,
     *         batch: ?Batch
     *     }
     * }
     */
    public function profile(Student $student): array
    {
        $student->loadMissing([
            'latestEnquiry',
            'activeEnrollment.course',
            'activeEnrollment.feeStructure',
            'activeEnrollment.admission.documents',
            'activeBatchStudent.batch.trainer',
            'enquiries',
        ]);

        $phase = $this->phaseFor($student);
        $leadSources = $this->leadSourceSummary($student);
        $dossier = $phase === ProfilePhase::Enrolled || $phase === ProfilePhase::ActiveStudent
            ? $this->dossierFor($student)
            : null;

        return [
            'phase' => $phase,
            'items' => $this->countersForPhase($student, $phase, $leadSources),
            'dossier' => $dossier,
            'recent_visits' => $phase->isLeadStage()
                ? $student->visits()
                    ->with(['enquiry.course', 'staff'])
                    ->orderByDesc('visit_date')
                    ->orderByDesc('id')
                    ->limit(5)
                    ->get()
                : new Collection,
            'lead_sources' => $leadSources,
        ];
    }

    /**
     * @return array{
     *     website_count: int,
     *     walk_in_count: int,
     *     meeting_for_counts: array<string, int>,
     *     first_source: ?LeadSource,
     *     latest_source: ?LeadSource,
     *     latest_meeting_for: ?string,
     *     latest_intent: ?string,
     *     headline: string,
     *     detail: ?string
     * }
     */
    protected function leadSourceSummary(Student $student): array
    {
        $enquiries = $student->enquiries->sortBy('created_at')->values();

        $websiteCount = $enquiries->where('lead_source', LeadSource::Website)->count();
        $walkInCount = $enquiries->where('lead_source', LeadSource::WalkIn)->count();

        $meetingForCounts = $enquiries
            ->filter(fn (Enquiry $enquiry): bool => filled($enquiry->meeting_for))
            ->groupBy(fn (Enquiry $enquiry): string => (string) $enquiry->meeting_for)
            ->map(fn ($group): int => $group->count())
            ->all();

        /** @var ?Enquiry $first */
        $first = $enquiries->first();
        $latest = $student->latestEnquiry ?? $enquiries->last();

        $firstSource = $first?->lead_source;
        $latestSource = $latest?->lead_source;
        $latestMeetingFor = $latest?->meeting_for;

        return [
            'website_count' => $websiteCount,
            'walk_in_count' => $walkInCount,
            'meeting_for_counts' => $meetingForCounts,
            'first_source' => $firstSource,
            'latest_source' => $latestSource,
            'latest_meeting_for' => $latestMeetingFor,
            'latest_intent' => $this->latestIntentLabel($latest),
            'headline' => $this->leadSourceHeadline($websiteCount, $walkInCount, $latestSource),
            'detail' => $this->leadSourceDetail(
                $firstSource,
                $latestSource,
                $websiteCount,
                $walkInCount,
                $meetingForCounts,
            ),
        ];
    }

    protected function latestIntentLabel(?Enquiry $enquiry): ?string
    {
        if (! $enquiry) {
            return null;
        }

        $source = $enquiry->lead_source?->label();
        $meetingFor = MeetingForOptions::label($enquiry->meeting_for);

        if ($source && $meetingFor) {
            return "{$source} for {$meetingFor}";
        }

        return $meetingFor ?? $source;
    }

    protected function leadSourceHeadline(int $websiteCount, int $walkInCount, ?LeadSource $latestSource): string
    {
        if ($websiteCount > 0 && $walkInCount > 0) {
            return 'Website + Walk-in lead';
        }

        if ($websiteCount > 0) {
            return 'Website lead';
        }

        if ($walkInCount > 0) {
            return 'Walk-in lead';
        }

        return $latestSource?->label() ?? 'Lead';
    }

    protected function leadSourceDetail(
        ?LeadSource $firstSource,
        ?LeadSource $latestSource,
        int $websiteCount,
        int $walkInCount,
        array $meetingForCounts,
    ): ?string {
        if ($websiteCount === 0 && $walkInCount === 0 && $meetingForCounts === []) {
            return null;
        }

        $parts = [];

        if ($websiteCount > 0) {
            $parts[] = $websiteCount === 1 ? '1 website enquiry' : "{$websiteCount} website enquiries";
        }

        if ($walkInCount > 0) {
            $parts[] = $walkInCount === 1 ? '1 walk-in enquiry' : "{$walkInCount} walk-in enquiries";
        }

        foreach ($meetingForCounts as $value => $count) {
            if ($count <= 0) {
                continue;
            }

            $label = MeetingForOptions::label((string) $value);
            $parts[] = $count === 1 ? "1 for {$label}" : "{$count} for {$label}";
        }

        $detail = implode(' · ', $parts);

        if (
            $firstSource
            && $latestSource
            && $firstSource !== $latestSource
            && in_array($firstSource, [LeadSource::Website, LeadSource::WalkIn], true)
            && in_array($latestSource, [LeadSource::Website, LeadSource::WalkIn], true)
        ) {
            $detail .= ' · First '.$firstSource->label().', latest '.$latestSource->label();
        }

        return $detail;
    }

    protected function phaseFor(Student $student): ProfilePhase
    {
        if ($student->activeEnrollment) {
            return $student->hasActiveBatch()
                ? ProfilePhase::ActiveStudent
                : ProfilePhase::Enrolled;
        }

        if (in_array($student->status, [
            StudentStatus::AdmissionSubmitted,
            StudentStatus::VerificationPending,
            StudentStatus::Approved,
        ], true)) {
            return ProfilePhase::Admission;
        }

        return ProfilePhase::Lead;
    }

    /**
     * @param  array{
     *     website_count: int,
     *     walk_in_count: int,
     *     first_source: ?LeadSource,
     *     latest_source: ?LeadSource,
     *     headline: string,
     *     detail: ?string
     * }  $leadSources
     * @return array<int, array{label: string, value: int|float|string|null}>
     */
    protected function countersForPhase(Student $student, ProfilePhase $phase, array $leadSources): array
    {
        return match ($phase) {
            ProfilePhase::Lead => $this->leadCounters($student, $leadSources),
            ProfilePhase::Admission => $this->admissionCounters($student, $leadSources),
            ProfilePhase::Enrolled => $this->enrolledCounters($student),
            ProfilePhase::ActiveStudent => $this->activeStudentCounters($student),
        };
    }

    /**
     * @param  array{website_count: int, walk_in_count: int, meeting_for_counts: array<string, int>}  $leadSources
     * @return array<int, array{label: string, value: int|float|string|null}>
     */
    protected function leadCounters(Student $student, array $leadSources): array
    {
        $counters = [
            ['label' => 'Visits', 'value' => $student->visits()->count()],
            ['label' => 'Enquiries', 'value' => $student->enquiries()->count()],
            ['label' => 'Website', 'value' => $leadSources['website_count']],
            ['label' => 'Walk-in', 'value' => $leadSources['walk_in_count']],
        ];

        foreach (MeetingForOptions::active() as $option) {
            $counters[] = [
                'label' => $option['label'],
                'value' => $leadSources['meeting_for_counts'][$option['value']] ?? 0,
            ];
        }

        return $counters;
    }

    /**
     * @param  array{website_count: int, walk_in_count: int, meeting_for_counts: array<string, int>}  $leadSources
     * @return array<int, array{label: string, value: int|float|string|null}>
     */
    protected function admissionCounters(Student $student, array $leadSources): array
    {
        $admission = $this->latestAdmission($student);

        $counters = [
            ['label' => 'Visits', 'value' => $student->visits()->count()],
            ['label' => 'Walk-in', 'value' => $leadSources['walk_in_count']],
        ];

        foreach (MeetingForOptions::active() as $option) {
            $count = $leadSources['meeting_for_counts'][$option['value']] ?? 0;

            if ($count > 0) {
                $counters[] = [
                    'label' => $option['label'],
                    'value' => $count,
                ];
            }
        }

        $counters[] = ['label' => 'Admission', 'value' => $admission?->status?->label() ?? '—'];

        return $counters;
    }

    /**
     * @return array<int, array{label: string, value: int|float|string|null}>
     */
    protected function enrolledCounters(Student $student): array
    {
        $enrollment = $student->activeEnrollment;
        $fees = $enrollment?->feeStructure;
        $batchName = $student->activeBatchStudent?->batch?->name;

        return [
            ['label' => 'Enrolled', 'value' => $enrollment?->enrolled_at?->format('d M Y') ?? '—'],
            ['label' => 'Batch', 'value' => $batchName ?? 'Not assigned'],
            ['label' => 'Paid', 'value' => $fees ? '₹'.number_format((float) $fees->paid_amount, 2) : '—'],
            ['label' => 'Pending', 'value' => $fees ? '₹'.number_format((float) $fees->pending_amount, 2) : '—'],
            ['label' => 'Visits', 'value' => $student->visits()->count()],
        ];
    }

    /**
     * @return ?array{
     *     photo: ?\App\Models\Document,
     *     enrollment: \App\Models\Enrollment,
     *     admission: ?Admission,
     *     fees: ?\App\Models\FeeStructure,
     *     batch: ?Batch
     * }
     */
    protected function dossierFor(Student $student): ?array
    {
        $enrollment = $student->activeEnrollment;

        if (! $enrollment) {
            return null;
        }

        $admission = $enrollment->admission;

        return [
            'photo' => $admission?->documentForType(DocumentType::Photo),
            'enrollment' => $enrollment,
            'admission' => $admission,
            'fees' => $enrollment->feeStructure,
            'batch' => $student->activeBatchStudent?->batch,
        ];
    }

    /**
     * @return array<int, array{label: string, value: int|float|string|null}>
     */
    protected function activeStudentCounters(Student $student): array
    {
        $percentage = $this->attendance->percentageForStudent($student);

        $counters = [
            ['label' => 'Batch', 'value' => $student->activeBatchStudent?->batch?->name ?? '—'],
            ['label' => 'Attendance', 'value' => $percentage !== null ? "{$percentage}%" : '—'],
        ];

        foreach (ActivityType::query()->enabled()->ordered()->get() as $type) {
            if ($type->supportsScoring()) {
                $counters[] = [
                    'label' => $type->name,
                    'value' => $this->activityAttendance->presentCountForStudent($student, $type),
                ];

                continue;
            }

            $summary = $this->activityAttendance->attendanceSummaryForStudent($student, $type);

            $counters[] = [
                'label' => $type->name,
                'value' => $summary['total'] > 0
                    ? "{$summary['present']}/{$summary['total']} present"
                    : 0,
            ];
        }

        return $counters;
    }

    protected function latestAdmission(Student $student): ?Admission
    {
        return $student->admissions()
            ->with('enquiry.course')
            ->latest()
            ->first();
    }
}
