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
use Illuminate\Support\Carbon;
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
    public function manualIn(Student $student, string $date, User $staff, ?string $time = null, bool $notifyParents = true): array
    {
        if ($blocked = $this->manualDateBlockedResult($date)) {
            return $blocked;
        }

        $resolvedTime = $this->normalizeManualInTime($time);
        if (! $resolvedTime['ok']) {
            return [
                'ok' => false,
                'message' => $resolvedTime['message'],
                'whatsapp' => null,
            ];
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

        $time = $resolvedTime['time'];
        $result = $this->processor->handleManualPunch($student, $roll, $date, $time, 'IN', $staff, $notifyParents);

        return [
            'ok' => true,
            'message' => "Check-in (IN) saved at {$time}.",
            'whatsapp' => $result['whatsapp'],
        ];
    }

    /**
     * Arrival time for a manual IN. Empty means now. A later time is rejected.
     *
     * @return array{ok: true, time: string}|array{ok: false, message: string}
     */
    public function normalizeManualInTime(?string $time): array
    {
        $raw = trim((string) $time);

        if ($raw === '') {
            return ['ok' => true, 'time' => now()->format('H:i:s')];
        }

        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $raw)) {
            return ['ok' => false, 'message' => 'Enter a valid arrival time.'];
        }

        $normalized = strlen($raw) === 5 ? $raw.':00' : $raw;

        if (Carbon::parse(now()->toDateString().' '.$normalized)->greaterThan(now())) {
            return ['ok' => false, 'message' => 'Arrival time cannot be later than now.'];
        }

        return ['ok' => true, 'time' => $normalized];
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
     * Mark student as Leave for one day (not Present). Requires a reason.
     *
     * @return array{ok: bool, message: string, whatsapp: null, saved?: int, skipped?: int}
     */
    public function markLeave(Student $student, string $date, User $staff, string $reason, ?Batch $batch = null): array
    {
        return $this->markLeaveRange($student, $date, $date, $staff, $reason, $batch);
    }

    /**
     * Apply Leave for each day from $fromDate through $toDate (inclusive).
     * Same daily Leave rows Hub / Dashboard already count. Cap: 14 days. From must be today or later.
     *
     * @return array{ok: bool, message: string, whatsapp: null, saved?: int, skipped?: int}
     */
    public function markLeaveRange(
        Student $student,
        string $fromDate,
        string $toDate,
        User $staff,
        string $reason,
        ?Batch $batch = null,
    ): array {
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

        try {
            $from = Carbon::parse($fromDate)->startOfDay();
            $to = Carbon::parse($toDate)->startOfDay();
        } catch (\Throwable) {
            return [
                'ok' => false,
                'message' => 'Enter a valid leave From and To date.',
                'whatsapp' => null,
            ];
        }

        $today = now()->startOfDay();

        if ($from->lt($today)) {
            return [
                'ok' => false,
                'message' => 'Leave can start from today only. Backdated leave is not allowed.',
                'whatsapp' => null,
            ];
        }

        if ($to->lt($from)) {
            return [
                'ok' => false,
                'message' => 'Leave To date must be on or after the From date.',
                'whatsapp' => null,
            ];
        }

        $dayCount = (int) $from->diffInDays($to) + 1;
        if ($dayCount > 14) {
            return [
                'ok' => false,
                'message' => 'Leave can cover at most 14 days at a time.',
                'whatsapp' => null,
            ];
        }

        $saved = 0;
        $skipped = 0;
        $skipReasons = [];

        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $dayResult = $this->applyLeaveForDay(
                $student,
                $batch,
                $day->toDateString(),
                $staff,
                $reason,
            );

            if ($dayResult['ok']) {
                $saved++;
            } else {
                $skipped++;
                if (count($skipReasons) < 3 && filled($dayResult['message'])) {
                    $skipReasons[] = $day->format('d M').': '.$dayResult['message'];
                }
            }
        }

        if ($saved === 0) {
            return [
                'ok' => false,
                'message' => $skipReasons !== []
                    ? 'Could not mark leave. '.implode(' ', $skipReasons)
                    : 'Could not mark leave for any day in this range.',
                'whatsapp' => null,
                'saved' => 0,
                'skipped' => $skipped,
            ];
        }

        CrmCacheInvalidator::afterAttendanceChange();

        $fromLabel = $from->format('d M Y');
        $toLabel = $to->format('d M Y');
        $rangeLabel = $fromLabel === $toLabel ? $fromLabel : "{$fromLabel} → {$toLabel}";
        $message = $saved === 1
            ? "Marked on Leave for {$rangeLabel}."
            : "Marked on Leave for {$saved} day(s) ({$rangeLabel}).";

        if ($skipped > 0) {
            $message .= " {$skipped} day(s) skipped";
            if ($skipReasons !== []) {
                $message .= ' ('.implode('; ', $skipReasons).')';
            }
            $message .= '.';
        }

        return [
            'ok' => true,
            'message' => $message,
            'whatsapp' => null,
            'saved' => $saved,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array{ok: bool, message: string}
     */
    private function applyLeaveForDay(
        Student $student,
        Batch $batch,
        string $date,
        User $staff,
        string $reason,
    ): array {
        $roll = $this->rollForStudent($student);
        if ($roll !== null && $date === now()->toDateString()) {
            $dayRow = app(LivePunchDashboardService::class)->studentDayRow($roll, $date, $student);
            if (($dayRow['current_state'] ?? null) === 'IN') {
                return [
                    'ok' => false,
                    'message' => 'still inside — mark OUT first',
                ];
            }
            if (($dayRow['pairs'] ?? []) !== []) {
                return [
                    'ok' => false,
                    'message' => 'already has an IN punch',
                ];
            }
        }

        $existing = Attendance::query()
            ->where('batch_id', $batch->id)
            ->where('student_id', $student->id)
            ->whereDate('attendance_date', $date)
            ->first();

        if ($existing?->checked_in_at !== null || $existing?->status === AttendanceStatus::Present) {
            return [
                'ok' => false,
                'message' => 'already marked present',
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

        return ['ok' => true, 'message' => ''];
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
