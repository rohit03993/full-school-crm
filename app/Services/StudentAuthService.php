<?php

namespace App\Services;

use App\Models\Student;
use DateTimeInterface;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class StudentAuthService
{
    public function formatDobPassword(DateTimeInterface $dateOfBirth): string
    {
        return $dateOfBirth->format('dmY');
    }

    public function hashPortalPassword(string $dobDdMmYyyy): string
    {
        return Hash::make($dobDdMmYyyy);
    }

    public function verifyPortalPassword(string $input, string $hashed): bool
    {
        return Hash::check($input, $hashed);
    }

    public function loginWithDob(string $mobile, string $password): ?Student
    {
        $student = Student::query()->where('mobile', preg_replace('/\D/', '', $mobile))->first();

        if (! $student || ! $student->portal_password) {
            return null;
        }

        return $this->verifyPortalPassword($password, $student->portal_password) ? $student : null;
    }

    public function loginWithOtp(string $mobile, string $otp): never
    {
        throw new RuntimeException('OTP login is not available in V1.');
    }
}
