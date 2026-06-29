<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Services\StudentAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPortalPasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_change_portal_password(): void
    {
        $auth = app(StudentAuthService::class);
        $default = config('institute.portal_default_password');

        $student = Student::query()->create([
            'name' => 'Portal Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => '9811000077',
            'status' => StudentStatus::Enrolled,
            'portal_password' => $auth->hashForNewStudent(),
        ]);

        $this->post(route('portal.login.submit'), [
            'mobile' => $student->mobile,
            'password' => $default,
        ])->assertRedirect(route('portal.dashboard'));

        $this->post(route('portal.password.change'), [
            'current_password' => $default,
            'password' => 'MyNewPass123',
            'password_confirmation' => 'MyNewPass123',
        ])->assertRedirect(route('portal.dashboard').'#more');

        $student->refresh();
        $this->assertTrue($auth->login($student->mobile, 'MyNewPass123') !== null);
        $this->assertNull($auth->login($student->mobile, $default));
    }
}
