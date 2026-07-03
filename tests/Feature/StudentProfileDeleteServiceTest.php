<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Models\Visit;
use App\Services\EnquiryService;
use App\Services\StudentProfileDeleteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentProfileDeleteServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleName::SuperAdmin->value);
        Role::findOrCreate(RoleName::Staff->value);
    }

    public function test_super_admin_can_delete_visitor_profile(): void
    {
        $admin = $this->createSuperAdmin();
        $staff = $this->createStaffUser();

        $course = Course::query()->create([
            'name' => 'Class 11 Science',
            'code' => 'C11-SCI',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 40000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Visitor To Delete',
            'mobile' => '9876501111',
            'course_id' => $course->id,
        ], $staff, LeadSource::WalkIn);

        $student = $enquiry->student;
        $studentId = $student->id;

        app(StudentProfileDeleteService::class)->delete($student, $admin);

        $this->assertNull(Student::query()->find($studentId));
        $this->assertSame(0, Enquiry::query()->count());
        $this->assertSame(0, Visit::query()->count());
    }

    public function test_staff_cannot_delete_profile(): void
    {
        $staff = $this->createStaffUser();

        $student = Student::query()->create([
            'name' => 'Protected Visitor',
            'father_name' => 'Parent',
            'date_of_birth' => '2008-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876502222',
            'status' => StudentStatus::Enquiry,
            'portal_password' => bcrypt('Student@2026'),
        ]);

        $this->expectException(ValidationException::class);

        app(StudentProfileDeleteService::class)->delete($student, $staff);
    }

    private function createSuperAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    private function createStaffUser(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }
}
