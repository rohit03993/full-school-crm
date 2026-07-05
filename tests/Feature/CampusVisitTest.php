<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\CampusVisitOutcome;
use App\Enums\CampusVisitPurpose;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\VisitMeetingAssignmentStatus;
use App\Enums\VisitStatus;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Models\Visit;
use App\Services\EnquiryService;
use App\Services\VisitMeetingAssignmentService;
use App\Services\VisitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CampusVisitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleName::Staff->value);
    }

    public function test_add_campus_visit_stores_purpose_without_changing_lead_status(): void
    {
        $staff = $this->createStaffUser();
        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Enrolled Campus',
            'mobile' => '9000000401',
            'discussion_summary' => 'Walk-in',
            'visit_status' => VisitStatus::Interested->value,
        ], $staff, LeadSource::WalkIn);

        $student = $enquiry->student;
        $this->attachActiveEnrollment($student, $enquiry);

        $visit = app(VisitService::class)->addCampusVisit(
            $student->fresh(['activeEnrollment']),
            $enquiry,
            [
                'visit_date' => now()->toDateString(),
                'campus_purpose' => CampusVisitPurpose::Fees->value,
                'discussion_summary' => 'Asked about pending installment.',
                'remarks' => null,
            ],
            $staff,
        );

        $enquiry->refresh();

        $this->assertSame(CampusVisitPurpose::Fees, $visit->campus_purpose);
        $this->assertNull($visit->campus_outcome);
        $this->assertTrue($visit->isCampusVisit());
        $this->assertSame(CampusVisitPurpose::Fees->label(), $visit->displayStatusLabel());
        $this->assertSame(VisitStatus::Interested, $enquiry->latest_visit_status);
    }

    public function test_close_enrolled_meeting_records_campus_outcome(): void
    {
        $reception = $this->createStaffUser('Reception');
        $counsellor = $this->createStaffUser('Counsellor');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Enrolled Close',
            'mobile' => '9000000402',
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
            'Parent here about fee receipt.',
        );

        app(VisitMeetingAssignmentService::class)->close(
            $assignment,
            $counsellor,
            'Issued duplicate receipt and explained ledger.',
            campusOutcome: CampusVisitOutcome::Resolved,
        );

        $assignment->refresh();

        $this->assertSame(VisitMeetingAssignmentStatus::Closed, $assignment->status);
        $this->assertTrue(
            Visit::query()
                ->where('discussion_summary', 'Issued duplicate receipt and explained ledger.')
                ->where('campus_outcome', CampusVisitOutcome::Resolved->value)
                ->exists()
        );
    }

    public function test_enrolled_assign_meeting_does_not_create_campus_visit(): void
    {
        $reception = $this->createStaffUser('Reception');
        $counsellor = $this->createStaffUser('Counsellor');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Assign Only',
            'mobile' => '9000000403',
            'discussion_summary' => 'Walk-in',
            'visit_status' => VisitStatus::Interested->value,
        ], $reception, LeadSource::WalkIn);

        $student = $enquiry->student;
        $this->attachActiveEnrollment($student, $enquiry);

        $campusVisitsBefore = Visit::query()
            ->where('student_id', $student->id)
            ->whereNotNull('campus_purpose')
            ->count();

        app(VisitMeetingAssignmentService::class)->assignFromFormData(
            $student->fresh(['activeEnrollment']),
            $enquiry,
            $reception,
            [
                'meeting_assign_to_user_id' => $counsellor->id,
                'meeting_handoff_notes' => 'Parent here about fee receipt.',
                'campus_purpose' => CampusVisitPurpose::Fees->value,
            ],
        );

        $campusVisitsAfter = Visit::query()
            ->where('student_id', $student->id)
            ->whereNotNull('campus_purpose')
            ->count();

        $this->assertSame($campusVisitsBefore, $campusVisitsAfter);
    }

    private function createStaffUser(string $name = 'Staff'): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'is_active' => true,
        ]);
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }

    private function attachActiveEnrollment(Student $student, Enquiry $enquiry): void
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
}
