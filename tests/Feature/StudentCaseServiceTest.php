<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\CampusVisitPurpose;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentCaseStatus;
use App\Enums\VisitStatus;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\StudentCase;
use App\Models\User;
use App\Services\CallLogService;
use App\Services\EnquiryService;
use App\Services\StudentCaseService;
use App\Services\VisitMeetingAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentCaseServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleName::Staff->value);
    }

    public function test_open_case_requires_enrolled_student(): void
    {
        $staff = $this->createStaffUser('Counsellor');
        $accountant = $this->createStaffUser('Accounts');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Lead Only',
            'mobile' => '9000000501',
            'discussion_summary' => 'Walk-in',
            'visit_status' => VisitStatus::Interested->value,
        ], $staff, LeadSource::WalkIn);

        $this->expectException(ValidationException::class);

        app(StudentCaseService::class)->open(
            $enquiry->student,
            CampusVisitPurpose::Fees,
            'Fee discount request',
            'Parent wants 20% off.',
            $accountant,
            $staff,
            'Please review fee waiver.',
        );
    }

    public function test_open_case_creates_number_assignment_and_links_visit(): void
    {
        [$student, $counsellor, $accountant] = $this->createEnrolledStudentScenario();

        $visit = $student->visits()->latest('id')->first();

        $case = app(StudentCaseService::class)->open(
            $student->fresh(['activeEnrollment']),
            CampusVisitPurpose::Fees,
            'Fee discount request',
            'Parent wants 20% discount on annual fee.',
            $accountant,
            $counsellor,
            'Parent met counsellor today — needs accounts review.',
            $visit,
        );

        $this->assertStringContainsString('-CASE-', $case->case_number);
        $this->assertSame(StudentCaseStatus::Open, $case->status);
        $this->assertSame($accountant->id, $case->current_assignee_user_id);
        $this->assertDatabaseHas('student_case_assignments', [
            'student_case_id' => $case->id,
            'to_user_id' => $accountant->id,
            'assigned_by_user_id' => $counsellor->id,
        ]);

        if ($visit) {
            $this->assertSame($case->id, $visit->fresh()->student_case_id);
        }
    }

    public function test_transfer_requires_note_and_updates_assignee(): void
    {
        [$student, $counsellor, $accountant] = $this->createEnrolledStudentScenario();
        $director = $this->createStaffUser('Director');

        $case = app(StudentCaseService::class)->open(
            $student->fresh(['activeEnrollment']),
            CampusVisitPurpose::Fees,
            'Fee discount request',
            null,
            $accountant,
            $counsellor,
            'Accounts to review.',
        );

        $updated = app(StudentCaseService::class)->transfer(
            $case,
            $director,
            $accountant,
            'Needs management approval for 10% discount.',
        );

        $this->assertSame($director->id, $updated->current_assignee_user_id);
        $this->assertSame(2, $updated->assignments()->count());
    }

    public function test_close_case_records_closing_note(): void
    {
        [$student, $counsellor, $accountant] = $this->createEnrolledStudentScenario();

        $case = app(StudentCaseService::class)->open(
            $student->fresh(['activeEnrollment']),
            CampusVisitPurpose::Fees,
            'Fee discount request',
            null,
            $accountant,
            $counsellor,
            'Accounts to review.',
        );

        $closed = app(StudentCaseService::class)->close(
            $case,
            $accountant,
            'Approved 10% discount for one term.',
        );

        $this->assertSame(StudentCaseStatus::Closed, $closed->status);
        $this->assertSame('Approved 10% discount for one term.', $closed->closing_note);
        $this->assertNotNull($closed->closed_at);
    }

    public function test_log_for_case_links_call_without_lead_pipeline_side_effects(): void
    {
        [$student, $counsellor, $accountant] = $this->createEnrolledStudentScenario();

        $case = app(StudentCaseService::class)->open(
            $student->fresh(['activeEnrollment']),
            CampusVisitPurpose::Fees,
            'Fee discount request',
            null,
            $accountant,
            $counsellor,
            'Accounts to review.',
        );

        $callsBefore = $student->calls()->count();

        $call = app(CallLogService::class)->logForCase($case, $accountant, [
            'call_connected' => true,
            'who_answered' => 'father',
            'call_notes' => 'Explained approved discount to parent on phone.',
            'duration_minutes' => 5,
        ]);

        $this->assertSame($case->id, $call->student_case_id);
        $this->assertSame($callsBefore + 1, $student->fresh()->calls()->count());
        $this->assertSame(1, $case->fresh()->calls()->count());
    }

    public function test_meeting_close_can_open_case_from_assignment_visit(): void
    {
        $reception = $this->createStaffUser('Reception');
        $counsellor = $this->createStaffUser('Counsellor');
        $accountant = $this->createStaffUser('Accounts');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Case From Meeting',
            'mobile' => '9000000502',
            'discussion_summary' => 'Walk-in',
            'visit_status' => VisitStatus::Interested->value,
        ], $reception, LeadSource::WalkIn);

        $student = $enquiry->student;
        $this->attachActiveEnrollment($student, $enquiry);

        $assignment = app(VisitMeetingAssignmentService::class)->assign(
            $student->fresh(['activeEnrollment']),
            $enquiry,
            $counsellor,
            $reception,
            'Parent wants fee waiver.',
        );

        $assignment = app(VisitMeetingAssignmentService::class)->close(
            $assignment,
            $counsellor,
            'Parent requested 20% discount — cannot decide on spot.',
            campusOutcome: \App\Enums\CampusVisitOutcome::Referred,
        );

        $case = app(StudentCaseService::class)->open(
            $student->fresh(['activeEnrollment']),
            CampusVisitPurpose::Fees,
            'Fee discount request',
            'Parent requested 20% discount — cannot decide on spot.',
            $accountant,
            $counsellor,
            'Please call parent and review waiver policy.',
            $assignment->resultingVisit,
        );

        $this->assertInstanceOf(StudentCase::class, $case);
        $this->assertSame($student->id, $case->student_id);
    }

    public function test_paginate_for_assignee_filters_by_status_and_search(): void
    {
        [$student, $counsellor, $accountant] = $this->createEnrolledStudentScenario();

        app(StudentCaseService::class)->open(
            $student->fresh(['activeEnrollment']),
            CampusVisitPurpose::Fees,
            'Fee discount request',
            null,
            $accountant,
            $counsellor,
            'Accounts to review.',
        );

        $service = app(StudentCaseService::class);

        $this->assertSame(1, $service->paginateForAssignee($accountant)->total());
        $this->assertSame(1, $service->statsForAssignee($accountant)['open']);
        $this->assertSame(1, $service->paginateForAssignee($accountant, search: $student->name)->total());
        $this->assertSame(0, $service->paginateForAssignee($accountant, statusFilter: 'closed')->total());
    }

    /**
     * @return array{0: Student, 1: User, 2: User}
     */
    protected function createEnrolledStudentScenario(): array
    {
        $reception = $this->createStaffUser('Reception');
        $counsellor = $this->createStaffUser('Counsellor');
        $accountant = $this->createStaffUser('Accounts');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Enrolled Case',
            'mobile' => '9000000503',
            'discussion_summary' => 'Walk-in',
            'visit_status' => VisitStatus::Interested->value,
        ], $reception, LeadSource::WalkIn);

        $student = $enquiry->student;
        $this->attachActiveEnrollment($student, $enquiry);

        return [$student->fresh(['activeEnrollment']), $counsellor, $accountant];
    }

    protected function attachActiveEnrollment(Student $student, Enquiry $enquiry): void
    {
        $course = $enquiry->course ?? Course::query()->findOrFail($enquiry->course_id);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-TEST-'.$student->id,
            'course_fee' => 40000,
            'discount_amount' => 0,
            'net_fee' => 40000,
            'use_installment_plan' => false,
            'status' => AdmissionStatus::Approved,
            'approved_at' => now(),
            'submitted_at' => now(),
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'enrollment_number' => 'ENR-'.$student->id,
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);
    }

    protected function createStaffUser(string $name = 'Staff'): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'is_active' => true,
        ]);
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }
}
