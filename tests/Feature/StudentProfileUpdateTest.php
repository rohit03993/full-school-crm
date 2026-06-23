<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentMobileService;
use App\Services\StudentUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_update_student_profile_details(): void
    {
        $staff = $this->createStaffUser();

        $student = Student::query()->create([
            'name' => 'Quick Lead',
            'mobile' => '9876543210',
            'status' => StudentStatus::Enquiry,
            'portal_password' => app(\App\Services\StudentAuthService::class)->hashForNewStudent(),
        ]);

        $updated = app(StudentUpdateService::class)->update($student, [
            'name' => 'Rohit Kumar',
            'mobile' => '9876543210',
            'father_name' => 'Anand Singh',
            'date_of_birth' => '2002-02-02',
            'gender' => Gender::Male->value,
            'email' => 'rohit@example.com',
            'city' => 'Agra',
            'category' => 'general',
        ], $staff);

        $this->assertSame('Rohit Kumar', $updated->name);
        $this->assertSame('Anand Singh', $updated->father_name);
        $this->assertSame('Agra', $updated->city);
        $this->assertNotNull($updated->portal_password);
    }

    public function test_staff_can_update_primary_and_alternate_mobile(): void
    {
        $staff = $this->createStaffUser();

        $student = Student::query()->create([
            'name' => 'Mobile Update',
            'mobile' => '9811000001',
            'status' => StudentStatus::Enrolled,
        ]);

        $updated = app(StudentUpdateService::class)->update($student, [
            'name' => 'Mobile Update',
            'mobile' => '9811000005',
            'alternate_mobile' => '9811000099',
            'category' => 'general',
        ], $staff);

        $this->assertSame('9811000005', $updated->mobile);
        $this->assertSame('9811000099', $updated->alternate_mobile);
    }

    public function test_duplicate_primary_mobile_is_rejected(): void
    {
        Student::query()->create([
            'name' => 'Other Student',
            'mobile' => '9811000008',
            'status' => StudentStatus::Enrolled,
        ]);

        $student = Student::query()->create([
            'name' => 'Current Student',
            'mobile' => '9811000001',
            'status' => StudentStatus::Enrolled,
        ]);

        $this->expectException(ValidationException::class);

        app(StudentMobileService::class)->validateForUpdate($student, '9811000008', null);
    }

    public function test_alternate_mobile_cannot_match_another_students_number(): void
    {
        Student::query()->create([
            'name' => 'Other Student',
            'mobile' => '9811000011',
            'status' => StudentStatus::Enrolled,
        ]);

        $student = Student::query()->create([
            'name' => 'Current Student',
            'mobile' => '9811000001',
            'status' => StudentStatus::Enrolled,
        ]);

        $this->expectException(ValidationException::class);

        app(StudentMobileService::class)->validateForUpdate($student, '9811000001', '9811000011');
    }

    protected function createStaffUser(): User
    {
        Role::findOrCreate(RoleName::Staff->value);

        $user = User::factory()->create([
            'is_active' => true,
        ]);

        $user->assignRole(RoleName::Staff->value);

        return $user;
    }
}
