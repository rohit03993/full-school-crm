<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Models\ActivityType;
use App\Enums\LeadSource;
use App\Enums\MeetingFor;
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
     *         school_count: int,
     *         coaching_count: int,
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
     *     school_count: int,
     *     coaching_count: int,
     *     first_source: ?LeadSource,
     *     latest_source: ?LeadSource,
     *     latest_meeting_for: ?MeetingFor,
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
        $schoolCount = $enquiries->where('meeting_for', MeetingFor::School)->count();
        $coachingCount = $enquiries->where('meeting_for', MeetingFor::Coaching)->count();

        /** @var ?Enquiry $first */
        $first = $enquiries->first();
        $latest = $student->latestEnquiry ?? $enquiries->last();

        $firstSource = $first?->lead_source;
        $latestSource = $latest?->lead_source;
        $latestMeetingFor = $latest?->meeting_for;

        return [
            'website_count' => $websiteCount,
            'walk_in_count' => $walkInCount,
            'school_count' => $schoolCount,
            'coaching_count' => $coachingCount,
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
                $schoolCount,
                $coachingCount,
            ),
        ];
    }

    protected function latestIntentLabel(?Enquiry $enquiry): ?string
    {
        if (! $enquiry) {
            return null;
        }

        $source = $enquiry->lead_source?->label();
        $meetingFor = $enquiry->meeting_for?->label();

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
        int $schoolCount,
        int $coachingCount,
    ): ?string {
        if ($websiteCount === 0 && $walkInCount === 0 && $schoolCount === 0 && $coachingCount === 0) {
            return null;
        }

        $parts = [];

        if ($websiteCount > 0) {
            $parts[] = $websiteCount === 1 ? '1 website enquiry' : "{$websiteCount} website enquiries";
        }

        if ($walkInCount > 0) {
            $parts[] = $walkInCount === 1 ? '1 walk-in enquiry' : "{$walkInCount} walk-in enquiries";
        }

        if ($schoolCount > 0) {
            $schoolLabel = MeetingFor::School->label();
            $parts[] = $schoolCount === 1 ? "1 for {$schoolLabel}" : "{$schoolCount} for {$schoolLabel}";
        }

        if ($coachingCount > 0) {
            $coachingLabel = MeetingFor::Coaching->label();
            $parts[] = $coachingCount === 1 ? "1 for {$coachingLabel}" : "{$coachingCount} for {$coachingLabel}";
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
     * @param  array{website_count: int, walk_in_count: int, school_count: int, coaching_count: int}  $leadSources
     * @return array<int, array{label: string, value: int|float|string|null}>
     */
    protected function leadCounters(Student $student, array $leadSources): array
    {
        return [
            ['label' => 'Visits', 'value' => $student->visits()->count()],
            ['label' => 'Enquiries', 'value' => $student->enquiries()->count()],
            ['label' => 'Website', 'value' => $leadSources['website_count']],
            ['label' => 'Walk-in', 'value' => $leadSources['walk_in_count']],
            ['label' => MeetingFor::School->label(), 'value' => $leadSources['school_count']],
            ['label' => MeetingFor::Coaching->label(), 'value' => $leadSources['coaching_count']],
        ];
    }

    /**
     * @param  array{website_count: int, walk_in_count: int, school_count: int, coaching_count: int}  $leadSources
     * @return array<int, array{label: string, value: int|float|string|null}>
     */
    protected function admissionCounters(Student $student, array $leadSources): array
    {
        $admission = $this->latestAdmission($student);

        return [
            ['label' => 'Visits', 'value' => $student->visits()->count()],
            ['label' => 'Walk-in', 'value' => $leadSources['walk_in_count']],
            ['label' => MeetingFor::School->label(), 'value' => $leadSources['school_count']],
            ['label' => MeetingFor::Coaching->label(), 'value' => $leadSources['coaching_count']],
            ['label' => 'Admission', 'value' => $admission?->status?->label() ?? '—'],
        ];
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
            $counters[] = [
                'label' => $type->name,
                'value' => $this->activityAttendance->presentCountForStudent($student, $type),
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
