<?php

namespace App\Services\Punch;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ManualBatchAttendanceService
{
    public function __construct(
        protected PunchAttendanceProcessor $processor,
        protected PunchLogService $logs,
        protected AttendanceService $attendance,
    ) {}

    /**
     * @return array{
     *     ok: bool,
     *     message: string,
     *     whatsapp: array{queued: bool, message: string}|null
     * }
     */
    public function manualIn(Student $student, string $date, User $staff): array
    {
        if ($blocked = $this->manualDateBlockedResult($date)) {
            return $blocked;
        }

        $roll = $this->rollForStudent($student);

        if ($roll === null) {
            return [
                'ok' => false,
                'message' => 'Add an active enrollment roll number before check-in.',
                'whatsapp' => null,
            ];
        }

        $dayRow = app(LivePunchDashboardService::class)->studentDayRow($roll, $date, $student);

        if (($dayRow['current_state'] ?? null) === 'IN') {
            return [
                'ok' => false,
                'message' => 'Already inside. Mark OUT first.',
                'whatsapp' => null,
            ];
        }

        $time = now()->format('H:i:s');
        $result = $this->processor->handleManualPunch($student, $roll, $date, $time, 'IN', $staff);

        return [
            'ok' => true,
            'message' => "Check-in (IN) saved at {$time}.",
            'whatsapp' => $result['whatsapp'],
        ];
    }

    /**
     * @return array{
     *     ok: bool,
     *     message: string,
     *     whatsapp: array{queued: bool, message: string}|null
     * }
     */
    public function manualOut(Student $student, string $date, User $staff): array
    {
        if ($blocked = $this->manualDateBlockedResult($date)) {
            return $blocked;
        }

        $roll = $this->rollForStudent($student);

        if ($roll === null) {
            return [
                'ok' => false,
                'message' => 'Add an active enrollment roll number before check-out.',
                'whatsapp' => null,
            ];
        }

        $dayRow = app(LivePunchDashboardService::class)->studentDayRow($roll, $date, $student);

        if (($dayRow['current_state'] ?? null) !== 'IN') {
            return [
                'ok' => false,
                'message' => 'Not inside. Mark IN first.',
                'whatsapp' => null,
            ];
        }

        $time = now()->format('H:i:s');
        $result = $this->processor->handleManualPunch($student, $roll, $date, $time, 'OUT', $staff);

        return [
            'ok' => true,
            'message' => "Check-out (OUT) saved at {$time}.",
            'whatsapp' => $result['whatsapp'],
        ];
    }

    /**
     * @param  array<int, string>  $marks  student_id => attendance status value
     * @return array{saved: int, in_punches: int, no_roll: int, whatsapp_queued: int, whatsapp_skipped: int}
     */
    public function save(Batch $batch, string $date, array $marks, User $staff): array
    {
        $this->attendance->assertManualDateIsToday($date);

        $activeStudentIds = BatchStudent::query()
            ->where('batch_id', $batch->id)
            ->where('is_active', true)
            ->pluck('student_id')
            ->all();

        $stats = [
            'saved' => 0,
            'in_punches' => 0,
            'no_roll' => 0,
            'whatsapp_queued' => 0,
            'whatsapp_skipped' => 0,
        ];

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

                    $result = $this->processor->handleManualPunch(
                        $student,
                        $roll,
                        $date,
                        now()->format('H:i:s'),
                        'IN',
                        $staff,
                    );
                    $stats['in_punches']++;
                    $this->tallyWhatsapp($stats, $result['whatsapp']);
                } else {
                    $this->markStatusOnly($batch, $student, $date, $status, $staff);
                }

                $stats['saved']++;
            }
        });

        return $stats;
    }

    /**
     * @return array{ok: false, message: string, whatsapp: null}|null
     */
    private function manualDateBlockedResult(string $date): ?array
    {
        try {
            $this->attendance->assertManualDateIsToday($date);
        } catch (ValidationException $exception) {
            return [
                'ok' => false,
                'message' => (string) ($exception->errors()['date'][0] ?? 'Manual attendance can only be marked for today.'),
                'whatsapp' => null,
            ];
        }

        return null;
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

    /**
     * @param  array{saved: int, in_punches: int, no_roll: int, whatsapp_queued: int, whatsapp_skipped: int}  $stats
     * @param  array{queued: bool, message: string}  $whatsapp
     */
    private function tallyWhatsapp(array &$stats, array $whatsapp): void
    {
        if ($whatsapp['queued']) {
            $stats['whatsapp_queued']++;
        } else {
            $stats['whatsapp_skipped']++;
        }
    }
}
