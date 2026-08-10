<?php

namespace App\Services;

use App\Enums\HomeworkCheckStatus;
use App\Enums\LicenseFeature;
use App\Models\Attendance;
use App\Models\HomeworkCheck;
use App\Models\HomeworkAssignment;
use App\Models\Student;
use App\Models\User;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use Carbon\Carbon;

class AskCrmStudentDataService
{
    public function __construct(
        protected AttendanceService $attendance,
        protected HomeworkCheckService $homeworkChecks,
        protected HomeworkAssignmentService $homeworkAssignments,
        protected StudentCounterService $counters,
    ) {}

    /**
     * Read-only CRM snapshot — same kind of data staff see on the student profile.
     *
     * @return array<string, mixed>
     */
    public function snapshot(User $user, Student $student, ?string $referencedDate = null): array
    {
        $student->loadMissing([
            'activeEnrollment.feeStructure',
            'activeEnrollment.course',
            'activeEnrollment.academicSession',
            'activeBatchStudent.batch',
            'latestEnquiry',
        ]);

        $batch = $student->activeBatchStudent?->batch;
        $studentId = (int) $student->id;

        return [
            'meta' => [
                'today' => now()->toDateString(),
                'referenced_date' => $referencedDate,
            ],
            'student' => [
                'id' => $studentId,
                'name' => $student->name,
                'father_name' => $student->father_name,
                'status' => $student->status->label(),
                'class' => $batch?->name,
                'course' => $student->activeEnrollment?->course?->name,
                'session' => $student->activeEnrollment?->academicSession?->name,
                'roll' => $student->activeEnrollment?->enrollment_number,
                'mobile' => $student->mobile,
                'email' => $student->email,
            ],
            'profile_summary' => $this->profileSummary($student),
            'attendance' => $this->attendanceSnapshot($student, $batch?->id, $referencedDate),
            'fees' => $this->feesSnapshot($user, $student),
            'homework' => $this->homeworkSnapshot($studentId, $referencedDate),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function profileSummary(Student $student): array
    {
        $profile = $this->counters->profile($student);

        return [
            'phase' => $profile['phase']->value,
            'counters' => $profile['items'],
            'lead_source' => [
                'headline' => $profile['lead_sources']['headline'] ?? null,
                'detail' => $profile['lead_sources']['detail'] ?? null,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function attendanceSnapshot(Student $student, ?int $batchId, ?string $referencedDate): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Attendance)) {
            return ['enabled' => false];
        }

        $today = now()->toDateString();
        $todayRow = $batchId ? $this->attendanceRowForDate($student->id, $batchId, $today) : null;
        $month = $this->attendance->monthToDateSummaryForStudent($student);

        $recentDays = [];

        if ($batchId) {
            $recentDays = Attendance::query()
                ->where('student_id', $student->id)
                ->where('batch_id', $batchId)
                ->whereDate('attendance_date', '>=', now()->subDays(45)->toDateString())
                ->orderByDesc('attendance_date')
                ->limit(45)
                ->get()
                ->map(fn (Attendance $row): array => [
                    'date' => $row->attendance_date->toDateString(),
                    'status' => $row->status->label(),
                    'checked_in_at' => $row->checked_in_at?->format('h:i A'),
                    'checked_out_at' => $row->checked_out_at?->format('h:i A'),
                ])
                ->values()
                ->all();
        }

        $onReferencedDate = null;

        if (filled($referencedDate) && $batchId) {
            $onReferencedDate = [
                'date' => $referencedDate,
                'record' => $this->attendanceRowForDate($student->id, $batchId, $referencedDate),
            ];
        }

        return [
            'enabled' => true,
            'has_active_class' => $batchId !== null,
            'today' => $todayRow ?? [
                'date' => $today,
                'status' => 'not_marked_yet',
            ],
            'month' => $month,
            'recent_days' => $recentDays,
            'on_referenced_date' => $onReferencedDate,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function attendanceRowForDate(int $studentId, int $batchId, string $date): ?array
    {
        $row = Attendance::query()
            ->where('student_id', $studentId)
            ->where('batch_id', $batchId)
            ->whereDate('attendance_date', $date)
            ->first();

        if (! $row) {
            return [
                'date' => $date,
                'status' => 'not_marked',
            ];
        }

        return [
            'date' => $date,
            'status' => $row->status->label(),
            'checked_in_at' => $row->checked_in_at?->format('h:i A'),
            'checked_out_at' => $row->checked_out_at?->format('h:i A'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function feesSnapshot(User $user, Student $student): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Fees)) {
            return ['enabled' => false];
        }

        if (! CrmAccess::canViewFees($user)) {
            return [
                'enabled' => true,
                'can_view' => false,
            ];
        }

        $fees = $student->activeEnrollment?->feeStructure;

        if (! $fees) {
            return [
                'enabled' => true,
                'can_view' => true,
                'has_fee_structure' => false,
            ];
        }

        $tuitionPending = (float) $fees->pending_amount;
        $totalPending = (float) $fees->totalCollectiblePending();

        return [
            'enabled' => true,
            'can_view' => true,
            'has_fee_structure' => true,
            'course_fee' => (float) $fees->course_fee,
            'discount_amount' => (float) $fees->discount_amount,
            'net_fee' => (float) $fees->net_fee,
            'tuition_pending' => $tuitionPending,
            'tuition_pending_formatted' => number_format($tuitionPending, 2),
            'paid_amount' => (float) $fees->paid_amount,
            'paid_amount_formatted' => number_format((float) $fees->paid_amount, 2),
            'total_pending' => $totalPending,
            'total_pending_formatted' => number_format($totalPending, 2),
            'is_clear' => $tuitionPending <= 0.009,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function homeworkSnapshot(int $studentId, ?string $referencedDate = null): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Homework)) {
            return ['enabled' => false];
        }

        $today = now()->toDateString();
        $allChecks = $this->homeworkChecks->forStudent($studentId, 60);

        $todayChecks = $allChecks
            ->filter(fn (HomeworkCheck $check): bool => $check->checked_on?->toDateString() === $today)
            ->map(fn (HomeworkCheck $check): array => $this->formatHomeworkCheck($check))
            ->values()
            ->all();

        [$weekFrom, $weekTo] = [
            now()->copy()->startOfWeek()->toDateString(),
            now()->copy()->endOfWeek()->toDateString(),
        ];

        $weekChecks = $allChecks
            ->filter(function (HomeworkCheck $check) use ($weekFrom, $weekTo): bool {
                $date = $check->checked_on?->toDateString();

                return $date !== null && $date >= $weekFrom && $date <= $weekTo;
            })
            ->map(fn (HomeworkCheck $check): array => $this->formatHomeworkCheck($check))
            ->values()
            ->all();

        $historyByDate = $allChecks
            ->groupBy(fn (HomeworkCheck $check): string => $check->checked_on?->toDateString() ?? 'unknown')
            ->map(function ($checks, string $date): array {
                $formatted = $checks
                    ->map(fn (HomeworkCheck $check): array => $this->formatHomeworkCheck($check))
                    ->values()
                    ->all();

                return [
                    'date' => $date,
                    'checks' => $formatted,
                    'done_count' => collect($formatted)->where('status', HomeworkCheckStatus::Done->label())->count(),
                    'not_done_count' => collect($formatted)->where('status', HomeworkCheckStatus::NotDone->label())->count(),
                ];
            })
            ->sortKeysDesc()
            ->values()
            ->all();

        $onReferencedDate = null;

        if (filled($referencedDate)) {
            $dateChecks = $allChecks
                ->filter(fn (HomeworkCheck $check): bool => $check->checked_on?->toDateString() === $referencedDate)
                ->map(fn (HomeworkCheck $check): array => $this->formatHomeworkCheck($check))
                ->values()
                ->all();

            $onReferencedDate = [
                'date' => $referencedDate,
                'checks' => $dateChecks,
                'marked' => count($dateChecks) > 0,
                'done_count' => collect($dateChecks)->where('status', HomeworkCheckStatus::Done->label())->count(),
                'not_done_count' => collect($dateChecks)->where('status', HomeworkCheckStatus::NotDone->label())->count(),
            ];
        }

        $student = Student::query()->find($studentId);
        $assignments = [];

        if ($student) {
            $assignments = $this->homeworkAssignments->assignmentsForStudentProfile($student)
                ->map(fn (HomeworkAssignment $assignment): array => [
                    'title' => $assignment->title,
                    'batch' => $assignment->batch?->name,
                    'published_at' => $assignment->published_at?->toDateString(),
                    'viewed_on_portal' => $assignment->views->isNotEmpty(),
                ])
                ->values()
                ->all();
        }

        return [
            'enabled' => true,
            'today' => [
                'date' => $today,
                'checks' => $todayChecks,
                'marked_count' => count($todayChecks),
                'done_count' => collect($todayChecks)->where('status', HomeworkCheckStatus::Done->label())->count(),
                'not_done_count' => collect($todayChecks)->where('status', HomeworkCheckStatus::NotDone->label())->count(),
                'unmarked_today' => count($todayChecks) === 0,
            ],
            'this_week' => [
                'from' => $weekFrom,
                'to' => $weekTo,
                'not_done_count' => $this->homeworkChecks->notDoneCountThisWeek($studentId),
                'done_count' => collect($weekChecks)->where('status', HomeworkCheckStatus::Done->label())->count(),
                'checks' => $weekChecks,
            ],
            'on_referenced_date' => $onReferencedDate,
            'history_by_date' => $historyByDate,
            'recent_checks' => $allChecks
                ->take(30)
                ->map(fn (HomeworkCheck $check): array => $this->formatHomeworkCheck($check))
                ->values()
                ->all(),
            'portal_assignments' => $assignments,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatHomeworkCheck(HomeworkCheck $check): array
    {
        return [
            'date' => $check->checked_on?->toDateString(),
            'subject' => $check->subject_name,
            'topic' => $check->topic,
            'status' => $check->status->label(),
            'class' => $check->batch?->name,
        ];
    }
}
