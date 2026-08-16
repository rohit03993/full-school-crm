<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\Student;
use Illuminate\Support\Facades\Hash;

class StudentAuthService
{
    public const LOGIN_MODE_SHARED = 'shared';

    /** @deprecated DOB login removed — kept for legacy settings rows only. */
    public const LOGIN_MODE_DOB = 'dob';

    public function portalLoginMode(): string
    {
        return self::LOGIN_MODE_SHARED;
    }

    public function defaultPortalPasswordPlain(): string
    {
        return (string) config('institute.portal_default_password', 'Student@2026');
    }

    public function sharedPortalPasswordHash(): ?string
    {
        $hash = Setting::getValue('portal.shared_password_hash');

        if (filled($hash)) {
            return (string) $hash;
        }

        $plain = $this->defaultPortalPasswordPlain();
        $hash = $this->hashPortalPassword($plain);

        Setting::setValue('portal.shared_password_hash', $hash, 'portal');
        Setting::setValue('portal.login_mode', self::LOGIN_MODE_SHARED, 'portal');

        return $hash;
    }

    public function hashPortalPassword(string $plain): string
    {
        return Hash::make($plain);
    }

    public function verifyPortalPassword(string $input, string $hashed): bool
    {
        return Hash::check($input, $hashed);
    }

    /**
     * Hash used when creating or refreshing a student's portal login.
     */
    public function hashForNewStudent(): ?string
    {
        return $this->sharedPortalPasswordHash();
    }

    /**
     * Ensure an enrolled student can sign in — sets default portal password if missing.
     */
    public function ensurePortalLoginForStudent(Student $student): void
    {
        $defaultHash = $this->sharedPortalPasswordHash();

        if ($defaultHash && blank($student->portal_password)) {
            $student->update(['portal_password' => $defaultHash]);
        }
    }

    public function login(string $mobile, string $password): ?Student
    {
        $students = $this->findStudentsByLoginMobile($mobile);

        if ($students->isEmpty()) {
            return null;
        }

        $matched = null;
        foreach ($students as $student) {
            if ($this->verifyStudentPassword($student, $password)) {
                $matched = $student;
                break;
            }
        }

        if (! $matched) {
            return null;
        }

        $preferred = $this->preferredStudentForLogin($students, $mobile) ?? $matched;

        if ($preferred->is($matched) || $this->verifyStudentPassword($preferred, $password) || $this->usesInstituteDefaultPassword($preferred)) {
            $active = $preferred;
        } else {
            $active = $matched;
        }

        if (blank($active->portal_password) || $this->hasLegacyDobPortalPassword($active)) {
            $active->update(['portal_password' => $this->sharedPortalPasswordHash()]);
        }

        return $active;
    }

    public function changePassword(Student $student, string $currentPassword, string $newPassword): bool
    {
        if (! $this->verifyStudentPassword($student, $currentPassword)) {
            return false;
        }

        $student->update([
            'portal_password' => $this->hashPortalPassword($newPassword),
        ]);

        return true;
    }

    /**
     * Students reachable by this phone: primary mobile or alternate (parent) mobile.
     *
     * @return \Illuminate\Support\Collection<int, Student>
     */
    public function findStudentsByLoginMobile(string $mobile): \Illuminate\Support\Collection
    {
        $digits = \App\Support\IndianMobileNumber::normalize($mobile);

        if ($digits === null) {
            return collect();
        }

        return Student::query()
            ->with('activeEnrollment')
            ->where(function ($query) use ($digits): void {
                $query->where('mobile', $digits)
                    ->orWhere('alternate_mobile', $digits);
            })
            ->orderBy('name')
            ->get();
    }

    public function findStudentByMobile(string $mobile): ?Student
    {
        $students = $this->findStudentsByLoginMobile($mobile);

        return $this->preferredStudentForLogin($students, $mobile);
    }

    /**
     * Prefer the student whose primary mobile matches the login number.
     *
     * @param  \Illuminate\Support\Collection<int, Student>  $students
     */
    public function preferredStudentForLogin(\Illuminate\Support\Collection $students, string $mobile): ?Student
    {
        if ($students->isEmpty()) {
            return null;
        }

        $digits = \App\Support\IndianMobileNumber::normalize($mobile);

        return $students->first(fn (Student $student): bool => $student->mobile === $digits)
            ?? $students->first();
    }

    public function loginWithVerifiedOtp(string $mobile): ?Student
    {
        $student = $this->findStudentByMobile($mobile);

        if (! $student) {
            return null;
        }

        $this->ensurePortalLoginForStudent($student);

        return $student;
    }

    /**
     * Switch active child after parent/student login. Student must belong to the login mobile.
     */
    public function studentAccessibleWithLoginMobile(Student $student, string $loginMobile): bool
    {
        $digits = \App\Support\IndianMobileNumber::normalize($loginMobile);

        if ($digits === null) {
            return false;
        }

        return $student->mobile === $digits || $student->alternate_mobile === $digits;
    }

    public function portalLoginHint(): string
    {
        return 'Parents and students: sign in with the mobile on the student record (or alternate/parent mobile). Use the institute portal password, or a WhatsApp OTP.';
    }

    /**
     * Plain-text hint for staff (never stored).
     */
    public function portalPasswordHintForStudent(Student $student): string
    {
        if (filled($student->portal_password)) {
            return 'Default institute password (or student\'s own password if they changed it). Set under Settings → Institute Settings.';
        }

        return 'Default institute password — set under Settings → Institute Settings.';
    }

    public function usesInstituteDefaultPassword(Student $student): bool
    {
        if (blank($student->portal_password)) {
            return true;
        }

        $defaultHash = $this->sharedPortalPasswordHash();

        if ($defaultHash !== null && hash_equals($student->portal_password, $defaultHash)) {
            return true;
        }

        return $this->hasLegacyDobPortalPassword($student);
    }

    public function verifyStudentPassword(Student $student, string $plain): bool
    {
        if (filled($student->portal_password)
            && $this->verifyPortalPassword($plain, $student->portal_password)) {
            return true;
        }

        if (! $this->usesInstituteDefaultPassword($student)) {
            return false;
        }

        $defaultHash = $this->sharedPortalPasswordHash();

        return $defaultHash !== null && $this->verifyPortalPassword($plain, $defaultHash);
    }

    /**
     * Students created before shared-password login still have DOB (DDMMYYYY) hashed here.
     */
    public function hasLegacyDobPortalPassword(Student $student): bool
    {
        if (blank($student->date_of_birth) || blank($student->portal_password)) {
            return false;
        }

        return $this->verifyPortalPassword(
            $student->date_of_birth->format('dmY'),
            $student->portal_password,
        );
    }
}
