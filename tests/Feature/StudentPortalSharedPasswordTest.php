<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Setting;
use App\Models\Student;
use App\Services\InstituteSettingsService;
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

    public function test_changing_the_school_password_updates_students_still_on_the_old_default(): void
    {
        $auth = app(StudentAuthService::class);
        $oldHash = $auth->hashPortalPassword('Student@2026');
        Setting::setValue('portal.shared_password_hash', $oldHash, 'portal');

        $onDefault = Student::query()->create([
            'name' => 'Still Default',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '9818208001',
            'status' => StudentStatus::Enrolled,
            'portal_password' => $oldHash,
        ]);
        $ownPassword = Student::query()->create([
            'name' => 'Own Password',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-02-02',
            'gender' => Gender::Female,
            'mobile' => '9818208002',
            'status' => StudentStatus::Enrolled,
            'portal_password' => $auth->hashPortalPassword('MyOwn@99'),
        ]);

        app(InstituteSettingsService::class)->save([
            'portal_shared_password' => '123456789',
        ]);

        $this->assertNotNull($auth->login('9818208001', '123456789'));
        $this->assertNull($auth->login('9818208001', 'Student@2026'));
        $this->assertNotNull($auth->login('9818208002', 'MyOwn@99'));
        $this->assertNull($auth->login('9818208002', '123456789'));
        $this->assertNotSame($oldHash, $onDefault->fresh()->portal_password);
        $this->assertTrue($auth->verifyPortalPassword('MyOwn@99', (string) $ownPassword->fresh()->portal_password));
    }
}
