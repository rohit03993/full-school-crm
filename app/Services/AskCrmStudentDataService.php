<?php

namespace App\Services;

use App\Enums\HomeworkCheckStatus;
use App\Enums\LicenseFeature;
use App\Models\Attendance;
use App\Models\HomeworkCheck;
use App\Models\Student;
use App\Models\User;
use App\Support\CrmAccess;
use App\Support\FeatureGate;

class AskCrmStudentDataService
{
    public function __construct(
        protected AttendanceService $attendance,
        protected HomeworkCheckService $homeworkChecks,
    ) {}

    /**
     * Read-only CRM snapshot for a student — used by Ask CRM (never invent data in AI replies).
     *
     * @return array<string, mixed>
     */
    public function snapshot(User $user, Student $student): array
    {
        $student->loadMissing([
            'activeEnrollment.feeStructure',
            'activeBatchStudent.batch',
        ]);

        $batch = $student->activeBatchStudent?->batch;

        return [
            'student' => [
                'id' => (int) $student->id,
                'name' => $student->name,
                'class' => $batch?->name,
                'roll' => $student->activeEnrollment?->enrollment_number,
                'mobile' => $student->mobile,
            ],
            'attendance' => $this->attendanceSnapshot($student, $batch?->id),
            'fees' => $this->feesSnapshot($user, $student),
            'homework' => $this->homeworkSnapshot((int) $student->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function attendanceSnapshot(Student $student, ?int $batchId): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Attendance)) {
            return ['enabled' => false];
        }

        $today = null;

        if ($batchId) {
            $row = Attendance::query()
                ->where('student_id', $student->id)
                ->where('batch_id', $batchId)
                ->whereDate('attendance_date', now()->toDateString())
                ->first();

            if ($row) {
                $today = [
                    'status' => $row->status->label(),
                    'checked_in_at' => $row->checked_in_at?->format('h:i A'),
                    'checked_out_at' => $row->checked_out_at?->format('h:i A'),
                    'date' => now()->toDateString(),
                ];
            } else {
                $today = [
                    'status' => 'not_marked_yet',
                    'date' => now()->toDateString(),
                ];
            }
        }

        $month = $this->attendance->monthToDateSummaryForStudent($student);

        return [
            'enabled' => true,
            'has_active_class' => $batchId !== null,
            'today' => $today,
            'month' => $month,
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
    public function homeworkSnapshot(int $studentId): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Homework)) {
            return ['enabled' => false];
        }

        $today = now()->toDateString();
        $todayChecks = HomeworkCheck::query()
            ->where('student_id', $studentId)
            ->whereDate('checked_on', $today)
            ->orderByDesc('id')
            ->get()
            ->map(fn (HomeworkCheck $check): array => $this->formatHomeworkCheck($check))
            ->values()
            ->all();

        $recent = $this->homeworkChecks->forStudent($studentId, 15)
            ->map(fn (HomeworkCheck $check): array => $this->formatHomeworkCheck($check))
            ->values()
            ->all();

        $notDoneThisWeek = $this->homeworkChecks->notDoneCountThisWeek($studentId);

        [$weekFrom, $weekTo] = [
            now()->copy()->startOfWeek()->toDateString(),
            now()->copy()->endOfWeek()->toDateString(),
        ];

        $weekChecks = HomeworkCheck::query()
            ->where('student_id', $studentId)
            ->whereDate('checked_on', '>=', $weekFrom)
            ->whereDate('checked_on', '<=', $weekTo)
            ->orderByDesc('checked_on')
            ->orderByDesc('id')
            ->get()
            ->map(fn (HomeworkCheck $check): array => $this->formatHomeworkCheck($check))
            ->values()
            ->all();

        $doneThisWeek = collect($weekChecks)->where('status', HomeworkCheckStatus::Done->label())->count();

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
                'not_done_count' => $notDoneThisWeek,
                'done_count' => $doneThisWeek,
                'checks' => $weekChecks,
            ],
            'recent' => $recent,
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
