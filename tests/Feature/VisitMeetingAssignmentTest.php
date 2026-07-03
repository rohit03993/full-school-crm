<?php

namespace Tests\Feature;

use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\VisitMeetingAssignmentStatus;
use App\Enums\VisitStatus;
use App\Models\Student;
use App\Models\User;
use App\Models\Visit;
use App\Services\EnquiryService;
use App\Services\VisitMeetingAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class VisitMeetingAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleName::Staff->value);
        Role::findOrCreate(RoleName::SuperAdmin->value);
    }

    public function test_assign_creates_open_assignment_and_database_notification(): void
    {
        $reception = $this->createStaffUser('Reception');
        $counsellor = $this->createStaffUser('Counsellor');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Walk-in Student',
            'mobile' => '9000000301',
            'discussion_summary' => 'Fee enquiry',
            'visit_status' => VisitStatus::Interested->value,
        ], $reception, LeadSource::WalkIn);

        $student = $enquiry->student;

        $assignment = app(VisitMeetingAssignmentService::class)->assign(
            $student,
            $enquiry,
            $counsellor,
            $reception,
            'Parent wants fee details for Class 11.',
        );

        $this->assertSame(VisitMeetingAssignmentStatus::Open, $assignment->status);
        $this->assertDatabaseHas('visit_meeting_assignments', [
            'student_id' => $student->id,
            'assigned_to_user_id' => $counsellor->id,
            'handoff_notes' => 'Parent wants fee details for Class 11.',
        ]);
        $this->assertDatabaseCount('notifications', 1);

        $open = app(VisitMeetingAssignmentService::class)->paginateOpenForStaff($counsellor);
        $this->assertSame(1, $open->total());
    }

    public function test_close_creates_visit_and_clears_open_assignment(): void
    {
        $reception = $this->createStaffUser('Reception');
        $counsellor = $this->createStaffUser('Counsellor');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Close Test',
            'mobile' => '9000000302',
            'discussion_summary' => 'Walk-in',
            'visit_status' => VisitStatus::Interested->value,
        ], $reception, LeadSource::WalkIn);

        $student = $enquiry->student;

        $assignment = app(VisitMeetingAssignmentService::class)->assign(
            $student,
            $enquiry,
            $counsellor,
            $reception,
            'Walk-in visit',
        );

        app(VisitMeetingAssignmentService::class)->close(
            $assignment,
            $counsellor,
            'Discussed fee plan and batch timing.',
            VisitStatus::Interested,
        );

        $assignment->refresh();

        $this->assertSame(VisitMeetingAssignmentStatus::Closed, $assignment->status);
        $this->assertNull(app(VisitMeetingAssignmentService::class)->openForStudent($student));
        $this->assertGreaterThanOrEqual(2, Visit::query()->count());
        $this->assertTrue(
            Visit::query()->where('discussion_summary', 'Discussed fee plan and batch timing.')->exists()
        );
    }

    public function test_only_one_open_assignment_per_student(): void
    {
        $reception = $this->createStaffUser('Reception');
        $counsellor = $this->createStaffUser('Counsellor');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Duplicate Test',
            'mobile' => '9000000303',
            'discussion_summary' => 'Walk-in',
            'visit_status' => VisitStatus::Interested->value,
        ], $reception, LeadSource::WalkIn);

        $student = $enquiry->student;

        app(VisitMeetingAssignmentService::class)->assign($student, $enquiry, $counsellor, $reception, 'First');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(VisitMeetingAssignmentService::class)->assign($student, $enquiry, $counsellor, $reception, 'Second');
    }

    public function test_profile_banner_visible_to_assignee_and_assigner(): void
    {
        $reception = $this->createStaffUser('Reception');
        $counsellor = $this->createStaffUser('Counsellor');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Banner Test',
            'mobile' => '9000000304',
            'discussion_summary' => 'Walk-in',
            'visit_status' => VisitStatus::Interested->value,
        ], $reception, LeadSource::WalkIn);

        $student = $enquiry->student;

        app(VisitMeetingAssignmentService::class)->assign($student, $enquiry, $counsellor, $reception, 'Handoff text');

        $service = app(VisitMeetingAssignmentService::class);

        $forCounsellor = $service->profileMeetingAssignment($student, $counsellor);
        $forReception = $service->profileMeetingAssignment($student, $reception);

        $this->assertTrue($forCounsellor['is_mine']);
        $this->assertTrue($forCounsellor['can_close']);
        $this->assertFalse($forReception['is_mine']);
        $this->assertFalse($forReception['can_close']);
        $this->assertSame('Handoff text', $forCounsellor['handoff_notes']);
    }

    public function test_stats_and_closed_list_for_assigned_staff(): void
    {
        $reception = $this->createStaffUser('Reception');
        $counsellor = $this->createStaffUser('Counsellor');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Stats Test',
            'mobile' => '9000000306',
            'discussion_summary' => 'Walk-in',
            'visit_status' => VisitStatus::Interested->value,
        ], $reception, LeadSource::WalkIn);

        $student = $enquiry->student;
        $service = app(VisitMeetingAssignmentService::class);

        $assignment = $service->assign($student, $enquiry, $counsellor, $reception, 'Handoff');

        $this->assertSame(['open' => 1, 'closed' => 0, 'total' => 1], $service->statsForStaff($counsellor));

        $service->close($assignment, $counsellor, 'Closed with notes.', VisitStatus::Interested);

        $this->assertSame(['open' => 0, 'closed' => 1, 'total' => 1], $service->statsForStaff($counsellor));
        $this->assertSame(1, $service->paginateForStaff($counsellor, 'closed')->total());
    }

    public function test_assign_from_form_data_skips_when_toggle_off(): void
    {
        $reception = $this->createStaffUser('Reception');
        $counsellor = $this->createStaffUser('Counsellor');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Toggle Off',
            'mobile' => '9000000305',
            'discussion_summary' => 'Walk-in',
            'visit_status' => VisitStatus::Interested->value,
        ], $reception, LeadSource::WalkIn);

        $result = app(VisitMeetingAssignmentService::class)->assignFromFormData(
            $enquiry->student,
            $enquiry,
            $reception,
            ['assign_meeting' => false],
        );

        $this->assertNull($result);
        $this->assertDatabaseCount('visit_meeting_assignments', 0);
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
}
