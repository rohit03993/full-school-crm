<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\StudentStatus;
use App\Filament\Pages\StudentProfilePage;
use App\Filament\Pages\StudentSearchPage;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\CrmPermissionSyncService;
use App\Services\EnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentProfileDeleteActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(CrmPermissionSyncService::class)->sync();
    }

    public function test_super_admin_sees_delete_student_action(): void
    {
        $admin = $this->createSuperAdmin();
        $student = $this->createEnquiryStudent($admin);

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->assertActionVisible('deleteStudent');
    }

    public function test_staff_does_not_see_delete_student_action(): void
    {
        $admin = $this->createSuperAdmin();
        $staff = $this->createCounsellor();
        $student = $this->createEnquiryStudent($admin);

        $this->actingAs($staff);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->assertActionHidden('deleteStudent');
    }

    public function test_super_admin_can_delete_student_from_profile(): void
    {
        $admin = $this->createSuperAdmin();
        $student = $this->createEnquiryStudent($admin);
        $studentId = $student->id;
        $mobile = $student->mobile;

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->callAction('deleteStudent')
            ->assertHasNoActionErrors()
            ->assertNotified()
            ->assertRedirect(StudentSearchPage::getUrl());

        $this->assertNull(Student::query()->find($studentId));
        $this->assertNull(Student::withTrashed()->find($studentId));
        $this->assertFalse(Student::query()->where('mobile', $mobile)->exists());
    }

    protected function createSuperAdmin(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createCounsellor(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(StaffJobRole::Counsellor->value);

        return $user;
    }

    protected function createEnquiryStudent(User $staff): Student
    {
        $course = Course::query()->create([
            'name' => 'Class 11 Science',
            'code' => 'DEL-11',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 40000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Visitor To Delete',
            'father_name' => 'Parent',
            'date_of_birth' => '2008-01-01',
            'gender' => Gender::Male->value,
            'mobile' => '9876503333',
            'course_id' => $course->id,
        ], $staff, LeadSource::WalkIn);

        $student = $enquiry->student;

        $this->assertSame(StudentStatus::Enquiry, $student->status);

        return $student;
    }
}
