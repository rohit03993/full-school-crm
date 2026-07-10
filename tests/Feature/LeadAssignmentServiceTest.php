<?php

namespace Tests\Feature;

use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\VisitStatus;
use App\Models\Student;
use App\Models\User;
use App\Services\CallQueueService;
use App\Services\EnquiryService;
use App\Services\LeadAssignmentService;
use App\Services\VisitMeetingAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeadAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleName::Staff->value);
        Role::findOrCreate(RoleName::SuperAdmin->value);
    }

    public function test_first_calling_assignment_does_not_require_handoff_note(): void
    {
        $reception = $this->createStaffUser('Reception');
        $telecaller = $this->createStaffUser('Telecaller');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Fresh Lead',
            'mobile' => '9000000401',
            'discussion_summary' => 'Walk-in enquiry.',
            'visit_status' => VisitStatus::Interested->value,
        ], $reception, LeadSource::WalkIn);

        $updated = app(LeadAssignmentService::class)->assignForCalling($enquiry, $telecaller, $reception);

        $this->assertSame($telecaller->id, $updated->meeting_with_user_id);
        $this->assertSame($reception->id, $updated->calling_assigned_by_user_id);
        $this->assertNotNull($updated->calling_assigned_at);
    }

    public function test_reassign_to_other_staff_requires_handoff_note(): void
    {
        $reception = $this->createStaffUser('Reception');
        $firstCaller = $this->createStaffUser('Caller A');
        $secondCaller = $this->createStaffUser('Caller B');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Reassign Lead',
            'mobile' => '9000000402',
            'discussion_summary' => 'Needs callback.',
            'visit_status' => VisitStatus::Interested->value,
        ], $reception, LeadSource::WalkIn);

        app(LeadAssignmentService::class)->assignForCalling($enquiry, $firstCaller, $reception);

        $this->expectException(ValidationException::class);

        app(LeadAssignmentService::class)->assignForCalling($enquiry, $secondCaller, $reception);
    }

    public function test_reassign_stores_handoff_note_and_assigned_by(): void
    {
        $counsellor = $this->createStaffUser('Counsellor');
        $firstCaller = $this->createStaffUser('Caller A');
        $secondCaller = $this->createStaffUser('Caller B');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Handoff Lead',
            'mobile' => '9000000403',
            'discussion_summary' => 'Parent asked for fee breakup.',
            'visit_status' => VisitStatus::Interested->value,
        ], $counsellor, LeadSource::WalkIn);

        app(LeadAssignmentService::class)->assignForCalling($enquiry, $firstCaller, $counsellor);

        $updated = app(LeadAssignmentService::class)->assignForCalling(
            $enquiry->fresh(),
            $secondCaller,
            $counsellor,
            'Parent wants a call back after 5 PM with hostel details.',
        );

        $this->assertSame($secondCaller->id, $updated->meeting_with_user_id);
        $this->assertSame($counsellor->id, $updated->calling_assigned_by_user_id);
        $this->assertSame(
            'Parent wants a call back after 5 PM with hostel details.',
            $updated->calling_handoff_note
        );
    }

    public function test_close_meeting_handoff_assigns_telecaller_with_note(): void
    {
        $reception = $this->createStaffUser('Reception');
        $counsellor = $this->createStaffUser('Counsellor');
        $telecaller = $this->createStaffUser('Telecaller');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Close Handoff',
            'mobile' => '9000000404',
            'discussion_summary' => 'Walk-in',
            'visit_status' => VisitStatus::Interested->value,
        ], $reception, LeadSource::WalkIn);

        $student = $enquiry->student;

        $assignment = app(VisitMeetingAssignmentService::class)->assign(
            $student,
            $enquiry,
            $counsellor,
            $reception,
            'Initial walk-in',
        );

        app(VisitMeetingAssignmentService::class)->close(
            $assignment,
            $counsellor,
            'Discussed Class 11 science batch. Parent will decide after board results.',
            VisitStatus::Interested,
        );

        app(LeadAssignmentService::class)->assignForCalling(
            $enquiry->fresh(),
            $telecaller,
            $counsellor,
            'Discussed Class 11 science batch. Parent will decide after board results.',
        );

        $enquiry->refresh();

        $this->assertSame($telecaller->id, $enquiry->meeting_with_user_id);
        $this->assertSame($counsellor->id, $enquiry->calling_assigned_by_user_id);
        $this->assertStringContainsString('board results', (string) $enquiry->calling_handoff_note);
    }

    public function test_call_queue_payload_includes_assignment_metadata(): void
    {
        $counsellor = $this->createStaffUser('Counsellor');
        $telecaller = $this->createStaffUser('Telecaller');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Queue Meta',
            'mobile' => '9000000405',
            'discussion_summary' => 'Walk-in',
            'visit_status' => VisitStatus::Interested->value,
        ], $counsellor, LeadSource::WalkIn);

        app(LeadAssignmentService::class)->assignForCalling(
            $enquiry,
            $telecaller,
            $counsellor,
            'Call before noon.',
        );

        $queue = app(CallQueueService::class);

        $student = $queue->dueQueue($telecaller)
            ->first(fn (Student $lead): bool => $lead->id === $enquiry->student_id);

        $this->assertNotNull($student);

        $payload = $queue->leadPayload($student);
        $this->assertSame($counsellor->name, $payload['assigned_by_name']);
        $this->assertSame('Call before noon.', $payload['calling_handoff_note']);
        $this->assertNotEmpty($payload['assigned_at_label']);
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
