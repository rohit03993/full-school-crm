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
        $student = $this->findStudentByMobile($mobile);

        if (! $student || ! $this->verifyStudentPassword($student, $password)) {
            return null;
        }

        if (blank($student->portal_password) || $this->hasLegacyDobPortalPassword($student)) {
            $student->update(['portal_password' => $this->sharedPortalPasswordHash()]);
        }

        return $student;
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

    public function portalLoginHint(): string
    {
        return 'Login with your mobile number and portal password. Use the default password from your institute until you change it.';
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

    protected function findStudentByMobile(string $mobile): ?Student
    {
        $digits = \App\Support\IndianMobileNumber::normalize($mobile);

        if ($digits === null) {
            return null;
        }

        return Student::query()->where('mobile', $digits)->first();
    }
}
