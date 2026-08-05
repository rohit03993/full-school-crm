<?php

namespace App\Services\Punch;

use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\User;

class PunchSubjectResolver
{
    public const TYPE_STUDENT = 'student';

    public const TYPE_STAFF = 'staff';

    public function __construct(
        protected PunchLogService $logs,
    ) {}

    /**
     * @return array{type: string, student: ?Student, user: ?User, pin: string}|null
     */
    public function resolve(string $pin): ?array
    {
        $pin = $this->logs->normalizeRoll($pin);

        if ($pin === '') {
            return null;
        }

        $student = $this->logs->findStudentByRoll($pin);

        if ($student) {
            return [
                'type' => self::TYPE_STUDENT,
                'student' => $student,
                'user' => null,
                'pin' => $pin,
            ];
        }

        $user = $this->findStaffByEmployeeCode($pin);

        if ($user) {
            return [
                'type' => self::TYPE_STAFF,
                'student' => null,
                'user' => $user,
                'pin' => $pin,
            ];
        }

        return null;
    }

    public function findStaffByEmployeeCode(string $code): ?User
    {
        $code = $this->logs->normalizeRoll($code);

        if ($code === '') {
            return null;
        }

        $profile = StaffProfile::query()
            ->whereRaw('UPPER(employee_code) = ?', [$code])
            ->with(['user' => fn ($query) => $query->where('is_active', true)])
            ->first();

        $user = $profile?->user;

        if (! $user || ! $user->is_active) {
            return null;
        }

        return $user;
    }
}
