<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentUpdateService;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        ]);

        $updated = app(StudentUpdateService::class)->update($student, [
            'name' => 'Rohit Kumar',
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
