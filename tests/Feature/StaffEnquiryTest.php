<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\EnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffEnquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_enquiry_creates_records_with_walk_in_source(): void
    {
        $staff = $this->createStaffUser();
        $course = $this->createCourse();

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Amit Kumar',
            'father_name' => 'Mr Kumar',
            'date_of_birth' => '2001-03-20',
            'gender' => Gender::Male->value,
            'mobile' => '9988776655',
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn->value,
            'meeting_with_user_id' => $staff->id,
            'discussion_summary' => 'Walk-in visit at front desk.',
            'visit_status' => 'interested',
        ], $staff, LeadSource::WalkIn);

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('enquiries', 1);
        $this->assertDatabaseCount('visits', 1);
        $this->assertSame(LeadSource::WalkIn, $enquiry->lead_source);
        $this->assertSame($staff->id, $enquiry->meeting_with_user_id);
    }

    public function test_quick_staff_enquiry_requires_only_name_and_mobile(): void
    {
        $staff = $this->createStaffUser();

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Quick Walk-in',
            'mobile' => '9001234567',
            'meeting_for' => 'school',
        ], $staff, LeadSource::WalkIn);

        $student = $enquiry->student;

        $this->assertSame('Quick Walk-in', $student->name);
        $this->assertSame('9001234567', $student->mobile);
        $this->assertNull($student->father_name);
        $this->assertNull($student->date_of_birth);
        $this->assertStringStartsWith('CRM-ENQ-', $enquiry->enquiry_number);
    }

    public function test_existing_student_cannot_receive_second_enquiry(): void
    {
        $staff = $this->createStaffUser();
        $courseA = $this->createCourse('DIP-1', 'Diploma A');
        $courseB = $this->createCourse('DIP-2', 'Diploma B');

        $student = Student::query()->create([
            'name' => 'Neha Singh',
            'father_name' => 'Mr Singh',
            'date_of_birth' => '1999-08-12',
            'gender' => Gender::Female,
            'mobile' => '9111222333',
            'status' => 'enquiry',
        ]);

        app(EnquiryService::class)->createForExistingStudent($student, [
            'course_id' => $courseA->id,
            'lead_source' => LeadSource::WalkIn->value,
            'meeting_with_user_id' => $staff->id,
            'discussion_summary' => 'First course interest.',
            'visit_status' => 'interested',
        ], $staff, LeadSource::WalkIn);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(EnquiryService::class)->createForExistingStudent($student, [
            'course_id' => $courseB->id,
            'lead_source' => LeadSource::Seminar->value,
            'meeting_with_user_id' => $staff->id,
            'discussion_summary' => 'Interested in second course.',
            'visit_status' => 'follow_up_required',
        ], $staff, LeadSource::Seminar);
    }

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }

    protected function createCourse(string $code = 'TEST-DIP', string $name = 'Diploma in Computer Applications'): Course
    {
        return Course::query()->create([
            'name' => $name,
            'code' => $code,
            'programme_category' => 'coaching',
            'duration' => 6,
            'duration_type' => 'months',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);
    }
}
