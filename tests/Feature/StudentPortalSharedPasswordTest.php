<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Setting;
use App\Models\Student;
use App\Services\StudentAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPortalSharedPasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_shared_portal_password_login(): void
    {
        Setting::setValue('portal.login_mode', StudentAuthService::LOGIN_MODE_SHARED, 'portal');
        Setting::setValue(
            'portal.shared_password_hash',
            app(StudentAuthService::class)->hashPortalPassword('Motion@2026'),
            'portal',
        );

        $student = Student::query()->create([
            'name' => 'Portal Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => '9811000099',
            'status' => StudentStatus::Enquiry,
            'portal_password' => null,
        ]);

        $loggedIn = app(StudentAuthService::class)->login('9811000099', 'Motion@2026');

        $this->assertNotNull($loggedIn);
        $this->assertSame($student->id, $loggedIn->id);
    }

    public function test_legacy_dob_password_student_can_login_with_institute_default(): void
    {
        $auth = app(StudentAuthService::class);
        $default = config('institute.portal_default_password');

        $student = Student::query()->create([
            'name' => 'Legacy Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2001-11-05',
            'gender' => Gender::Male,
            'mobile' => '8109462946',
            'status' => StudentStatus::Enrolled,
            'portal_password' => $auth->hashPortalPassword('05112001'),
        ]);

        $this->assertTrue($auth->hasLegacyDobPortalPassword($student));

        $loggedIn = $auth->login('8109462946', $default);

        $this->assertNotNull($loggedIn);
        $this->assertFalse($auth->hasLegacyDobPortalPassword($student->fresh()));
    }
}
