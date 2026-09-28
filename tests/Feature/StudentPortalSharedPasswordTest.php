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

    public function test_a_student_still_on_student_2026_gets_the_new_password_after_the_setting_was_already_saved(): void
    {
        $auth = app(StudentAuthService::class);
        Setting::setValue(
            'portal.shared_password_hash',
            $auth->hashPortalPassword('123456789'),
            'portal',
        );

        Student::query()->create([
            'name' => 'Still Old Default',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-03-03',
            'gender' => Gender::Male,
            'mobile' => '9818208001',
            'status' => StudentStatus::Enrolled,
            'portal_password' => $auth->hashPortalPassword('Student@2026'),
        ]);
        Student::query()->create([
            'name' => 'Own Password',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-04-04',
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
    }

    public function test_school_password_works_at_login_without_updating_every_student_first(): void
    {
        $auth = app(StudentAuthService::class);
        $oldHash = $auth->hashPortalPassword('OldSchool@1');
        Setting::setValue('portal.shared_password_hash', $auth->hashPortalPassword('123456789'), 'portal');

        foreach (['9818208001', '9818208003'] as $mobile) {
            Student::query()->create([
                'name' => 'Shared '.$mobile,
                'father_name' => 'Parent',
                'date_of_birth' => '2010-05-05',
                'gender' => Gender::Male,
                'mobile' => $mobile,
                'status' => StudentStatus::Enrolled,
                'portal_password' => $oldHash,
            ]);
        }

        $this->assertNotNull($auth->login('9818208001', '123456789'));
        $this->assertNull($auth->login('9818208001', 'OldSchool@1'));
        $this->assertNotNull($auth->login('9818208003', '123456789'));
    }

    public function test_login_finds_a_mobile_saved_with_country_code(): void
    {
        $auth = app(StudentAuthService::class);
        Setting::setValue('portal.shared_password_hash', $auth->hashPortalPassword('123456789'), 'portal');

        Student::query()->create([
            'name' => 'Country Code',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-06-06',
            'gender' => Gender::Male,
            'mobile' => '+91 9818208001',
            'status' => StudentStatus::Enrolled,
            'portal_password' => null,
        ]);

        $this->assertNotNull($auth->login('9818208001', '123456789'));
    }

    public function test_login_page_says_whether_the_mobile_or_the_password_is_wrong(): void
    {
        $auth = app(StudentAuthService::class);
        Setting::setValue('portal.shared_password_hash', $auth->hashPortalPassword('123456789'), 'portal');

        Student::query()->create([
            'name' => 'Known Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-07-07',
            'gender' => Gender::Male,
            'mobile' => '9818208001',
            'status' => StudentStatus::Enrolled,
            'portal_password' => null,
        ]);

        $this->post(route('portal.login.submit'), [
            'mobile' => '9818208099',
            'password' => '123456789',
        ])->assertSessionHasErrors([
            'mobile' => 'This mobile number is not saved on any student. Check Mobile or Alternate mobile on the student profile.',
        ]);

        $this->post(route('portal.login.submit'), [
            'mobile' => '9818208001',
            'password' => 'not-the-password',
        ])->assertSessionHasErrors([
            'password' => 'Wrong password. Use the Default student portal password from Setup → Institute settings.',
        ]);
    }
}
