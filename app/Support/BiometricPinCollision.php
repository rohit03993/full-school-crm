<?php

namespace App\Support;

use App\Models\Enrollment;
use App\Models\StaffProfile;

class BiometricPinCollision
{
    public static function normalize(?string $pin): string
    {
        return strtoupper(trim((string) $pin));
    }

    public static function enrollmentNumberExists(string $pin, ?int $ignoreEnrollmentId = null): bool
    {
        $pin = self::normalize($pin);

        if ($pin === '') {
            return false;
        }

        $query = Enrollment::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(enrollment_number) = ?', [$pin]);

        if ($ignoreEnrollmentId) {
            $query->whereKeyNot($ignoreEnrollmentId);
        }

        return $query->exists();
    }

    public static function employeeCodeExists(string $pin, ?int $ignoreStaffProfileId = null): bool
    {
        $pin = self::normalize($pin);

        if ($pin === '') {
            return false;
        }

        $query = StaffProfile::query()
            ->whereRaw('UPPER(employee_code) = ?', [$pin]);

        if ($ignoreStaffProfileId) {
            $query->whereKeyNot($ignoreStaffProfileId);
        }

        return $query->exists();
    }

    public static function staffCodeCollidesWithStudentRoll(string $employeeCode): bool
    {
        return self::enrollmentNumberExists($employeeCode);
    }

    public static function studentRollCollidesWithStaffCode(string $enrollmentNumber): bool
    {
        return self::employeeCodeExists($enrollmentNumber);
    }
}
