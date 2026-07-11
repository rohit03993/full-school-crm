<?php

namespace App\Services;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    /**
     * Manual / roll-call attendance may only be marked for today.
     */
    public function assertManualDateIsToday(string $date): void
    {
        $selected = Carbon::parse($date)->toDateString();
        $today = now()->toDateString();

        if ($selected !== $today) {
            throw ValidationException::withMessages([
                'date' => 'Manual attendance can only be marked for today. Backdated entries are not allowed.',
            ]);
        }
    }

    /**
     * @param  array<int, string>  $marks  student_id => attendance status value
     */
    public function saveBatchAttendance(Batch $batch, string $date, array $marks, User $staff): int
    {
        $this->assertManualDateIsToday($date);

        $activeStudentIds = BatchStudent::query()
            ->where('batch_id', $batch->id)
            ->where('is_active', true)
            ->pluck('student_id')
            ->all();

        if ($activeStudentIds === []) {
            throw ValidationException::withMessages([
                'batch_id' => 'This batch has no active students.',
            ]);
        }

        $saved = 0;

        DB::transaction(function () use ($batch, $date, $marks, $staff, $activeStudentIds, &$saved): void {
            foreach ($marks as $studentId => $statusValue) {
                $studentId = (int) $studentId;

                if (! in_array($studentId, $activeStudentIds, true)) {
                    continue;
                }

                $status = AttendanceStatus::tryFrom((string) $statusValue);

                if (! $status) {
                    continue;
                }

                Attendance::query()->updateOrCreate(
                    [
                        'batch_id' => $batch->id,
                        'student_id' => $studentId,
                        'attendance_date' => $date,
                    ],
                    [
                        'status' => $status,
                        'marked_by_user_id' => $staff->id,
                    ],
                );

                $saved++;
            }

            if ($saved > 0) {
                $this->audit->log(
                    'attendance_marked',
                    $batch,
                    null,
                    [
                        'batch_id' => $batch->id,
                        'batch_name' => $batch->name,
                        'attendance_date' => $date,
                        'records_saved' => $saved,
                    ],
                    user: $staff,
                );
            }
        });

        return $saved;
    }

    public function percentageForStudent(Student $student): ?float
    {
        return $this->monthToDateSummaryForStudent($student)['percentage'] ?? null;
    }

    /**
     * Month-to-date attendance for the current calendar month (through today).
     *
     * @return array{
     *     percentage: float,
     *     present_days: int,
     *     leave_days: int,
     *     credited_days: int,
     *     expected_days: int,
     *     absent_days: int,
     *     period_label: string,
     *     from: string,
     *     to: string,
     * }|null
     */
    public function monthToDateSummaryForStudent(Student $student): ?array
    {
        $today = now()->startOfDay();

        return $this->summaryForStudentInRange(
            $student,
            $today->copy()->startOfMonth(),
            $today,
        );
    }

    /**
     * Attendance summary for a calendar month (Y-m). Caps at today for the current month.
     *
     * @return array{
     *     percentage: float,
     *     present_days: int,
     *     leave_days: int,
     *     credited_days: int,
     *     expected_days: int,
     *     absent_days: int,
     *     period_label: string,
     *     from: string,
     *     to: string,
     * }|null
     */
    public function summaryForStudentInMonth(Student $student, string $yearMonth): ?array
    {
        $month = Carbon::createFromFormat('Y-m', $yearMonth)->startOfMonth();
        $end = $month->copy()->endOfMonth()->startOfDay();
        $today = now()->startOfDay();

        if ($end->greaterThan($today)) {
            $end = $today;
        }

        return $this->summaryForStudentInRange($student, $month, $end);
    }

    /**
     * @return array{
     *     percentage: float,
     *     present_days: int,
     *     leave_days: int,
     *     credited_days: int,
     *     expected_days: int,
     *     absent_days: int,
     *     period_label: string,
     *     from: string,
     *     to: string,
     * }|null
     */
    public function summaryForStudentInRange(Student $student, Carbon $rangeStart, Carbon $rangeEnd): ?array
    {
        $student->loadMissing('activeBatchStudent');

        $batchStudent = $student->activeBatchStudent;
        $batchId = $batchStudent?->batch_id;

        if (! $batchId) {
            return null;
        }

        $from = $rangeStart->copy()->startOfDay();
        $to = $rangeEnd->copy()->startOfDay();

        if ($from->greaterThan($to)) {
            return null;
        }

        $joined = $batchStudent->assigned_at
            ? Carbon::parse($batchStudent->assigned_at)->startOfDay()
            : $from;

        if ($joined->greaterThan($from)) {
            $from = $joined;
        }

        if ($from->greaterThan($to)) {
            return null;
        }

        $workingDates = $this->workingDatesBetween($from, $to);
        $expected = count($workingDates);

        if ($expected === 0) {
            return null;
        }

        $rows = Attendance::query()
            ->where('batch_id', $batchId)
            ->where('student_id', $student->id)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->get(['attendance_date', 'status']);

        $presentDates = [];
        $leaveDates = [];

        foreach ($rows as $row) {
            $date = $row->attendance_date->toDateString();

            if (! in_array($date, $workingDates, true)) {
                continue;
            }

            if ($row->status === AttendanceStatus::Present) {
                $presentDates[$date] = true;
            } elseif ($row->status === AttendanceStatus::Leave) {
                $leaveDates[$date] = true;
            }
        }

        $presentDays = count($presentDates);
        $leaveDays = count(array_diff_key($leaveDates, $presentDates));
        $creditLeave = (bool) config('attendance.percentage.credit_leave', true);
        $creditedDays = $presentDays + ($creditLeave ? $leaveDays : 0);
        $absentDays = max(0, $expected - $creditedDays);
        $percentage = round(($creditedDays / $expected) * 100, 1);

        return [
            'percentage' => $percentage,
            'present_days' => $presentDays,
            'leave_days' => $leaveDays,
            'credited_days' => $creditedDays,
            'expected_days' => $expected,
            'absent_days' => $absentDays,
            'period_label' => $from->format('d M').' – '.$to->format('d M Y'),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }

    /**
     * @return list<string> Y-m-d dates
     */
    public function workingDatesBetween(Carbon $from, Carbon $to): array
    {
        $weekendDays = array_map('intval', config('attendance.percentage.weekend_days', [0]));
        $dates = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            if (! in_array($cursor->dayOfWeek, $weekendDays, true)) {
                $dates[] = $cursor->toDateString();
            }

            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @return array<int, string> student_id => status value
     */
    public function marksForBatchDate(Batch $batch, string $date): array
    {
        return Attendance::query()
            ->where('batch_id', $batch->id)
            ->whereDate('attendance_date', $date)
            ->get()
            ->mapWithKeys(fn (Attendance $row): array => [$row->student_id => $row->status->value])
            ->all();
    }

    /**
     * @return array<int, array{
     *     status: string,
     *     checked_in_at: ?string,
     *     checked_out_at: ?string,
     *     is_inside: bool,
     *     punch_source: ?string,
     *     marked_by_name: ?string,
     *     source_label: string
     * }>
     */
    public function punchSnapshotForBatchDate(Batch $batch, string $date): array
    {
        return Attendance::query()
            ->with('markedBy:id,name')
            ->where('batch_id', $batch->id)
            ->whereDate('attendance_date', $date)
            ->get()
            ->mapWithKeys(function (Attendance $row): array {
                $checkedIn = $row->checked_in_at?->format('H:i');
                $checkedOut = $row->checked_out_at?->format('H:i');
                $staffName = $row->markedBy?->name;

                return [
                    $row->student_id => [
                        'status' => $row->status->value,
                        'checked_in_at' => $checkedIn,
                        'checked_out_at' => $checkedOut,
                        'is_inside' => $checkedIn !== null && $checkedOut === null,
                        'punch_source' => $row->punch_source,
                        'marked_by_name' => $staffName,
                        'source_label' => \App\Support\AttendanceSourceLabel::for($row->punch_source, $staffName),
                    ],
                ];
            })
            ->all();
    }

    /**
     * @return list<array{date: string, label: string, checked_in: int, checked_out: int, total: int}>
     */
    public function markedDateSummariesForBatch(Batch $batch, int $limit = 30): array
    {
        $rows = Attendance::query()
            ->where('batch_id', $batch->id)
            ->orderByDesc('attendance_date')
            ->get()
            ->groupBy(fn (Attendance $row): string => $row->attendance_date->toDateString());

        return $rows
            ->map(function ($group, string $date): array {
                return [
                    'date' => $date,
                    'label' => Carbon::parse($date)->format('d M Y'),
                    'checked_in' => $group->where('status', AttendanceStatus::Present)->count(),
                    'checked_out' => $group->whereNotNull('checked_out_at')->count(),
                    'total' => $group->count(),
                ];
            })
            ->sortByDesc('date')
            ->take($limit)
            ->values()
            ->all();
    }
}
