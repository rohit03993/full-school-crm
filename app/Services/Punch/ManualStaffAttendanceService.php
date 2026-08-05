<?php

namespace App\Services\Punch;

use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Validation\ValidationException;

class ManualStaffAttendanceService
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
    public function manualIn(User $staffMember, string $date, User $actor): array
    {
        if ($blocked = $this->manualDateBlockedResult($date)) {
            return $blocked;
        }

        $code = $this->staffCode($staffMember);

        if ($code === null) {
            return [
                'ok' => false,
                'message' => 'Add a Staff ID before check-in.',
                'whatsapp' => null,
            ];
        }

        if ($this->isInside($staffMember, $date)) {
            return [
                'ok' => false,
                'message' => 'Already inside. Mark OUT first.',
                'whatsapp' => null,
            ];
        }

        $time = now()->format('H:i:s');
        $result = $this->processor->handleManualStaffPunch($staffMember, $code, $date, $time, 'IN', $actor);

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
    public function manualOut(User $staffMember, string $date, User $actor): array
    {
        if ($blocked = $this->manualDateBlockedResult($date)) {
            return $blocked;
        }

        $code = $this->staffCode($staffMember);

        if ($code === null) {
            return [
                'ok' => false,
                'message' => 'Add a Staff ID before check-out.',
                'whatsapp' => null,
            ];
        }

        if (! $this->isInside($staffMember, $date)) {
            return [
                'ok' => false,
                'message' => 'Not inside. Mark IN first.',
                'whatsapp' => null,
            ];
        }

        $time = now()->format('H:i:s');
        $result = $this->processor->handleManualStaffPunch($staffMember, $code, $date, $time, 'OUT', $actor);

        return [
            'ok' => true,
            'message' => "Check-out (OUT) saved at {$time}.",
            'whatsapp' => $result['whatsapp'],
        ];
    }

    public function isInside(User $staffMember, string $date): bool
    {
        $row = StaffAttendance::query()
            ->where('user_id', $staffMember->id)
            ->whereDate('attendance_date', $date)
            ->first();

        return $row !== null
            && $row->checked_in_at !== null
            && $row->checked_out_at === null;
    }

    private function staffCode(User $staffMember): ?string
    {
        $staffMember->loadMissing('staffProfile');
        $code = $staffMember->staffProfile?->employee_code;

        return filled($code) ? $this->logs->normalizeRoll((string) $code) : null;
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
}
