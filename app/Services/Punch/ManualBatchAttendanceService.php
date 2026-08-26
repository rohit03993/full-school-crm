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
use App\Support\CrmCacheInvalidator;
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
     * Mark student as Leave for the day (not Present). Requires a reason.
     *
     * @return array{ok: bool, message: string, whatsapp: null}
     */
    public function markLeave(Student $student, string $date, User $staff, string $reason, ?Batch $batch = null): array
    {
        if ($blocked = $this->manualDateBlockedResult($date)) {
            return $blocked;
        }

        $reason = trim($reason);

        if ($reason === '') {
            return [
                'ok' => false,
                'message' => 'Enter a leave reason (pick a tag or type your own).',
                'whatsapp' => null,
            ];
        }

        $batch ??= $this->activeBatchForStudent($student);

        if (! $batch) {
            return [
                'ok' => false,
                'message' => 'Student is not in an active class section.',
                'whatsapp' => null,
            ];
        }

        $roll = $this->rollForStudent($student);
        if ($roll !== null) {
            $dayRow = app(LivePunchDashboardService::class)->studentDayRow($roll, $date, $student);
            if (($dayRow['current_state'] ?? null) === 'IN') {
                return [
                    'ok' => false,
                    'message' => 'Student is still inside. Mark OUT first, or clear today’s IN before Leave.',
                    'whatsapp' => null,
                ];
            }
            if (($dayRow['pairs'] ?? []) !== []) {
                return [
                    'ok' => false,
                    'message' => 'Student already has an IN punch today. Leave is only for students who did not attend.',
                    'whatsapp' => null,
                ];
            }
        }

        $existing = Attendance::query()
            ->where('batch_id', $batch->id)
            ->where('student_id', $student->id)
            ->whereDate('attendance_date', $date)
            ->first();

        if ($existing?->checked_in_at !== null) {
            return [
                'ok' => false,
                'message' => 'Student already has check-in today. Leave is only when they did not come.',
                'whatsapp' => null,
            ];
        }

        Attendance::query()->updateOrCreate(
            [
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'attendance_date' => $date,
            ],
            [
                'status' => AttendanceStatus::Leave,
                'checked_in_at' => null,
                'checked_out_at' => null,
                'punch_source' => 'roll_call',
                'leave_reason' => mb_substr($reason, 0, 255),
                'marked_by_user_id' => $staff->id,
            ],
        );

        CrmCacheInvalidator::afterAttendanceChange();

        return [
            'ok' => true,
            'message' => 'Marked on Leave.',
            'whatsapp' => null,
        ];
    }

    /**
     * Mark student Absent for the day (not Leave / not Present).
     *
     * @return array{ok: bool, message: string, whatsapp: null}
     */
    public function markAbsent(Student $student, string $date, User $staff, ?Batch $batch = null): array
    {
        if ($blocked = $this->manualDateBlockedResult($date)) {
            return $blocked;
        }

        $batch ??= $this->activeBatchForStudent($student);

        if (! $batch) {
            return [
                'ok' => false,
                'message' => 'Student is not in an active class section.',
                'whatsapp' => null,
            ];
        }

        $roll = $this->rollForStudent($student);
        if ($roll !== null) {
            $dayRow = app(LivePunchDashboardService::class)->studentDayRow($roll, $date, $student);
            if (($dayRow['current_state'] ?? null) === 'IN') {
                return [
                    'ok' => false,
                    'message' => 'Student is still inside. Mark OUT first before Absent.',
                    'whatsapp' => null,
                ];
            }
            if (($dayRow['pairs'] ?? []) !== []) {
                return [
                    'ok' => false,
                    'message' => 'Student already has an IN punch today. Cannot mark Absent.',
                    'whatsapp' => null,
                ];
            }
        }

        $existing = Attendance::query()
            ->where('batch_id', $batch->id)
            ->where('student_id', $student->id)
            ->whereDate('attendance_date', $date)
            ->first();

        if ($existing?->checked_in_at !== null) {
            return [
                'ok' => false,
                'message' => 'Student already has check-in today. Cannot mark Absent.',
                'whatsapp' => null,
            ];
        }

        Attendance::query()->updateOrCreate(
            [
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'attendance_date' => $date,
            ],
            [
                'status' => AttendanceStatus::Absent,
                'checked_in_at' => null,
                'checked_out_at' => null,
                'punch_source' => 'roll_call',
                'leave_reason' => null,
                'marked_by_user_id' => $staff->id,
            ],
        );

        CrmCacheInvalidator::afterAttendanceChange();

        return [
            'ok' => true,
            'message' => 'Marked Absent.',
            'whatsapp' => null,
        ];
    }

    private function activeBatchForStudent(Student $student): ?Batch
    {
        $batchId = BatchStudent::query()
            ->where('student_id', $student->id)
            ->where('is_active', true)
            ->value('batch_id');

        return $batchId ? Batch::query()->find($batchId) : null;
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

        if ($stats['saved'] > 0) {
            CrmCacheInvalidator::afterAttendanceChange();
        }

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
                'checked_in_at' => null,
                'checked_out_at' => null,
                'punch_source' => 'roll_call',
                'leave_reason' => null,
                'marked_by_user_id' => $staff->id,
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
