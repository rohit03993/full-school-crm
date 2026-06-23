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
     * @param  array<int, string>  $marks  student_id => attendance status value
     */
    public function saveBatchAttendance(Batch $batch, string $date, array $marks, User $staff): int
    {
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
        $student->loadMissing('activeBatchStudent');

        $batchId = $student->activeBatchStudent?->batch_id;

        if (! $batchId) {
            return null;
        }

        $total = Attendance::query()
            ->where('batch_id', $batchId)
            ->where('student_id', $student->id)
            ->count();

        if ($total === 0) {
            return null;
        }

        $present = Attendance::query()
            ->where('batch_id', $batchId)
            ->where('student_id', $student->id)
            ->where('status', AttendanceStatus::Present)
            ->count();

        return round(($present / $total) * 100, 1);
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
     * @return list<array{date: string, label: string, present: int, absent: int, leave: int, total: int}>
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
                    'present' => $group->where('status', AttendanceStatus::Present)->count(),
                    'absent' => $group->where('status', AttendanceStatus::Absent)->count(),
                    'leave' => $group->where('status', AttendanceStatus::Leave)->count(),
                    'total' => $group->count(),
                ];
            })
            ->sortByDesc('date')
            ->take($limit)
            ->values()
            ->all();
    }
}
