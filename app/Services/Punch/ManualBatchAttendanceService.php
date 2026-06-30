<?php

namespace App\Services\Punch;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ManualBatchAttendanceService
{
    public function __construct(
        protected PunchAttendanceProcessor $processor,
        protected PunchLogService $logs,
    ) {}

    /**
     * @param  array<int, string>  $marks  student_id => attendance status value
     * @return array{saved: int, in_punches: int, no_roll: int}
     */
    public function save(Batch $batch, string $date, array $marks, User $staff): array
    {
        $activeStudentIds = BatchStudent::query()
            ->where('batch_id', $batch->id)
            ->where('is_active', true)
            ->pluck('student_id')
            ->all();

        $stats = ['saved' => 0, 'in_punches' => 0, 'no_roll' => 0];

        DB::transaction(function () use ($batch, $date, $marks, $staff, $activeStudentIds, &$stats): void {
            foreach ($marks as $studentId => $statusValue) {
                $studentId = (int) $studentId;

                if (! in_array($studentId, $activeStudentIds, true)) {
                    continue;
                }

                $status = AttendanceStatus::tryFrom((string) $statusValue);

                if (! $status) {
                    continue;
                }

                $student = Student::query()->find($studentId);

                if (! $student) {
                    continue;
                }

                if ($status === AttendanceStatus::Present) {
                    $roll = $this->rollForStudent($student);

                    if ($roll === null) {
                        $this->markStatusOnly($batch, $student, $date, $status, $staff);
                        $stats['no_roll']++;

                        continue;
                    }

                    $this->processor->handleManualPunch(
                        $student,
                        $roll,
                        $date,
                        now()->format('H:i:s'),
                        'IN',
                        $staff,
                    );
                    $stats['in_punches']++;
                } else {
                    $this->markStatusOnly($batch, $student, $date, $status, $staff);
                }

                $stats['saved']++;
            }
        });

        return $stats;
    }

    public function manualOut(Student $student, string $date, User $staff): bool
    {
        $roll = $this->rollForStudent($student);

        if ($roll === null) {
            return false;
        }

        $this->processor->handleManualPunch(
            $student,
            $roll,
            $date,
            now()->format('H:i:s'),
            'OUT',
            $staff,
        );

        return true;
    }

    private function rollForStudent(Student $student): ?string
    {
        $roll = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('is_active', true)
            ->value('enrollment_number');

        return filled($roll) ? $this->logs->normalizeRoll((string) $roll) : null;
    }

    private function markStatusOnly(Batch $batch, Student $student, string $date, AttendanceStatus $status, User $staff): void
    {
        Attendance::query()->updateOrCreate(
            [
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'attendance_date' => $date,
            ],
            [
                'status' => $status,
                'marked_by_user_id' => $staff->id,
                'punch_source' => 'roll_call',
            ],
        );
    }
}
