<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrolledCallPurpose;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\VisitStatus;
use App\Filament\Pages\StudentCallsPage;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\CallLogService;
use App\Services\EnquiryService;
use App\Services\StudentCallsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentCallsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_enrolled_service_calls_and_filters_by_purpose(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createEnrolledStudent($staff);

        app(CallLogService::class)->logForEnrolledStudent($student, $staff, [
            'call_connected' => true,
            'who_answered' => 'father',
            'call_purpose' => EnrolledCallPurpose::Attendance->value,
            'call_notes' => 'Asked about missing attendance yesterday.',
        ]);

        app(CallLogService::class)->logForEnrolledStudent($student->fresh(['activeEnrollment']), $staff, [
            'call_connected' => true,
            'who_answered' => 'student',
            'call_purpose' => EnrolledCallPurpose::FeeQuery->value,
            'call_notes' => 'Discussed next fee installment due date.',
        ]);

        $service = app(StudentCallsService::class);

        $all = $service->paginate($service->normalizeFilters([]));
        $this->assertSame(2, $all->total());

        $attendance = $service->paginate($service->normalizeFilters([
            'purpose' => EnrolledCallPurpose::Attendance->value,
        ]));

        $this->assertSame(1, $attendance->total());
        $this->assertSame(EnrolledCallPurpose::Attendance, $attendance->first()->call_purpose);
        $this->assertSame($staff->id, $attendance->first()->user_id);
        $this->assertSame($student->id, $attendance->first()->student_id);
    }

    public function test_excludes_lead_calls_without_purpose(): void
    {
        $staff = $this->createStaffUser();
        $lead = $this->createLeadStudent($staff);

        app(CallLogService::class)->log($lead, $staff, [
            'call_connected' => true,
            'who_answered' => 'father',
            'visit_status' => VisitStatus::Interested->value,
            'call_notes' => 'Lead call about admission brochure.',
            'next_followup_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ]);

        $service = app(StudentCallsService::class);
        $this->assertSame(0, $service->summary($service->normalizeFilters([]))['total']);
    }

    public function test_student_calls_page_is_reachable(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $this->actingAs($admin);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        Livewire::test(StudentCallsPage::class)
            ->assertSuccessful()
            ->assertSee('Service calls for enrolled students');
    }

    protected function createStaffUser(string $name = 'Caller'): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true, 'name' => $name]);
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }

    protected function createLeadStudent(User $staff)
    {
        $course = Course::query()->create([
            'name' => 'Class 11',
            'code' => 'C11-SC-'.uniqid(),
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Lead Only',
            'mobile' => '9811001111',
            'course_id' => $course->id,
            'meeting_with_user_id' => $staff->id,
        ], $staff, LeadSource::WalkIn);

        return $enquiry->student;
    }

    protected function createEnrolledStudent(User $staff)
    {
        $course = Course::query()->create([
            'name' => 'Class 12',
            'code' => 'C12-SC-'.uniqid(),
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 80000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Enrolled Caller',
            'mobile' => '9811002222',
            'course_id' => $course->id,
            'meeting_with_user_id' => $staff->id,
            'visit_status' => VisitStatus::Interested->value,
        ], $staff, LeadSource::WalkIn);

        $student = $enquiry->student;

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-SC-'.$student->id,
            'course_fee' => 80000,
            'discount_amount' => 0,
            'net_fee' => 80000,
            'use_installment_plan' => false,
            'status' => AdmissionStatus::Approved,
            'approved_at' => now(),
            'submitted_at' => now(),
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'enrollment_number' => 'ENR-SC-'.$student->id,
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        $student->update(['status' => StudentStatus::Enrolled]);

        return $student->fresh(['activeEnrollment']);
    }
}
