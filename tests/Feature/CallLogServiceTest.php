<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\CallStatus;
use App\Enums\CampusVisitPurpose;
use App\Enums\CourseStatus;
use App\Enums\EnrolledCallPurpose;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\VisitStatus;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Services\CallLogService;
use App\Services\EnquiryService;
use App\Services\StudentCaseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CallLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_connected_call_is_logged_with_staff_name(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createLeadStudent($staff);

        $call = app(CallLogService::class)->log($student, $staff, [
            'call_connected' => true,
            'call_direction' => 'outgoing',
            'who_answered' => 'father',
            'visit_status' => VisitStatus::Interested->value,
            'call_notes' => 'Interested in Class 12 admission next month.',
            'next_followup_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ]);

        $student->refresh();

        $this->assertSame($staff->id, $call->user_id);
        $this->assertSame(CallStatus::Connected, $call->call_status);
        $this->assertSame(1, $student->total_calls);
        $this->assertNotNull($student->last_call_at);
        $this->assertDatabaseMissing('visits', [
            'student_id' => $student->id,
            'remarks' => 'Outgoing call',
        ]);
    }

    public function test_lead_call_updates_enquiry_visit_status(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createLeadStudent($staff);
        $enquiry = $student->enquiries()->latest()->firstOrFail();

        $this->assertSame(VisitStatus::Interested, $enquiry->latest_visit_status);

        app(CallLogService::class)->log($student, $staff, [
            'call_connected' => true,
            'who_answered' => 'student',
            'visit_status' => VisitStatus::AdmissionReady->value,
            'call_notes' => 'Ready for admission after campus visit discussion.',
            'next_followup_at' => now()->addDays(1)->format('Y-m-d H:i:s'),
        ]);

        $enquiry->refresh();

        $this->assertSame(VisitStatus::AdmissionReady, $enquiry->latest_visit_status);
    }

    public function test_enrolled_profile_call_does_not_update_enquiry_visit_status(): void
    {
        $staff = $this->createStaffUser();
        [$student, $enquiry] = $this->createEnrolledStudentWithEnquiry($staff);

        $this->assertSame(VisitStatus::Interested, $enquiry->latest_visit_status);
        $this->assertTrue(app(CallLogService::class)->isEnrolledCallContext($student));

        $call = app(CallLogService::class)->log($student->fresh(['activeEnrollment']), $staff, [
            'call_connected' => true,
            'who_answered' => 'father',
            'call_purpose' => EnrolledCallPurpose::FeeQuery->value,
            'call_notes' => 'Discussed pending fee installment with parent on phone.',
            'tags' => ['fee_due'],
        ]);

        $enquiry->refresh();
        $student->refresh();

        $this->assertSame(VisitStatus::Interested, $enquiry->latest_visit_status);
        $this->assertSame(EnrolledCallPurpose::FeeQuery, $call->call_purpose);
        $this->assertNull($call->visit_status_changed_to);
        $this->assertNull($call->enquiry_id);
        $this->assertSame(1, $student->total_calls);
    }

    public function test_enrolled_callback_sets_follow_up_without_visit_status(): void
    {
        $staff = $this->createStaffUser();
        [$student, $enquiry] = $this->createEnrolledStudentWithEnquiry($staff);
        $followUp = now()->addDay()->setTime(10, 0);

        $call = app(CallLogService::class)->logForEnrolledStudent($student->fresh(['activeEnrollment']), $staff, [
            'call_connected' => true,
            'who_answered' => 'mother',
            'call_purpose' => EnrolledCallPurpose::CallbackNeeded->value,
            'call_notes' => 'Parent asked for a callback tomorrow about documents.',
            'next_followup_at' => $followUp->format('Y-m-d H:i:s'),
        ]);

        $enquiry->refresh();
        $student->refresh();

        $this->assertSame(VisitStatus::Interested, $enquiry->latest_visit_status);
        $this->assertSame(EnrolledCallPurpose::CallbackNeeded, $call->call_purpose);
        $this->assertNotNull($student->next_call_followup_at);
        $this->assertTrue($student->next_call_followup_at->equalTo($followUp));
    }

    public function test_case_call_still_skips_lead_pipeline(): void
    {
        $staff = $this->createStaffUser();
        $assignee = $this->createStaffUser('Accounts');
        [$student, $enquiry] = $this->createEnrolledStudentWithEnquiry($staff);

        $case = app(StudentCaseService::class)->open(
            $student->fresh(['activeEnrollment']),
            CampusVisitPurpose::Fees,
            'Fee query case',
            null,
            $assignee,
            $staff,
            'Please call parent.',
        );

        $call = app(CallLogService::class)->logForCase($case, $assignee, [
            'call_connected' => true,
            'who_answered' => 'father',
            'call_notes' => 'Explained fee balance and next installment date.',
        ]);

        $enquiry->refresh();

        $this->assertSame($case->id, $call->student_case_id);
        $this->assertSame(VisitStatus::Interested, $enquiry->latest_visit_status);
        $this->assertNull($call->visit_status_changed_to);
        $this->assertNull($call->call_purpose);
    }

    public function test_three_not_connected_calls_block_student(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createLeadStudent($staff);
        $service = app(CallLogService::class);

        foreach (['no_answer', 'busy', 'switched_off'] as $status) {
            $service->log($student->fresh(), $staff, [
                'call_connected' => false,
                'call_status' => $status,
            ]);
        }

        $student->refresh();

        $this->assertTrue($student->is_call_blocked);
        $this->assertSame(3, $student->total_calls);
        $this->assertFalse($student->isCallable());
    }

    protected function createStaffUser(string $name = 'Telecaller One'): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true, 'name' => $name]);
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }

    protected function createLeadStudent(User $staff): Student
    {
        $course = Course::query()->create([
            'name' => 'Class 12 Commerce',
            'code' => 'C12-CALL',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 120000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Call Test Student',
            'mobile' => '9811223344',
            'course_id' => $course->id,
            'meeting_with_user_id' => $staff->id,
        ], $staff, LeadSource::WalkIn);

        return $enquiry->student;
    }

    /**
     * @return array{0: Student, 1: Enquiry}
     */
    protected function createEnrolledStudentWithEnquiry(User $staff): array
    {
        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'C10-ENR-'.uniqid(),
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 80000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Enrolled Call Student',
            'mobile' => '9811002299',
            'course_id' => $course->id,
            'meeting_with_user_id' => $staff->id,
            'visit_status' => VisitStatus::Interested->value,
        ], $staff, LeadSource::WalkIn);

        $student = $enquiry->student;

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-CALL-'.$student->id,
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
            'enrollment_number' => 'ENR-CALL-'.$student->id,
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        $student->update(['status' => StudentStatus::Enrolled]);

        return [$student->fresh(['activeEnrollment']), $enquiry->fresh()];
    }
}
