<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\VisitStatus;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Services\CallLogService;
use App\Services\CallQueueService;
use App\Services\LeadAssignmentService;
use App\Services\LeadTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CallQueueServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_future_follow_up_is_scheduled_not_due(): void
    {
        [$staff, $student] = $this->createAssignedLead();

        $student->update([
            'next_call_followup_at' => now()->addDays(2)->setTime(16, 0),
            'total_calls' => 1,
            'last_call_at' => now(),
        ]);

        $queue = app(CallQueueService::class);

        $this->assertSame(0, $queue->dueQueueCount($staff));
        $this->assertSame(1, $queue->scheduledQueueCount($staff));
        $this->assertFalse($queue->dueQueue($staff)->contains('id', $student->id));
        $this->assertTrue($queue->scheduledQueue($staff)->contains('id', $student->id));
    }

    public function test_follow_up_appears_in_due_queue_on_scheduled_date(): void
    {
        [$staff, $student] = $this->createAssignedLead();

        $student->update([
            'next_call_followup_at' => now()->setTime(16, 0),
            'total_calls' => 1,
            'last_call_at' => now()->subDay(),
        ]);

        $queue = app(CallQueueService::class);

        $this->assertSame(1, $queue->dueQueueCount($staff));
        $this->assertSame(0, $queue->scheduledQueueCount($staff));
    }

    public function test_uncalled_assigned_lead_is_due_without_follow_up_date(): void
    {
        [$staff, $student] = $this->createAssignedLead();

        $this->assertSame(1, app(CallQueueService::class)->dueQueueCount($staff));
        $this->assertTrue(
            app(CallQueueService::class)->dueQueue($staff)->pluck('id')->contains($student->id),
        );
    }

    public function test_call_log_sets_follow_up_and_moves_between_queues(): void
    {
        [$staff, $student] = $this->createAssignedLead();
        $queue = app(CallQueueService::class);

        app(CallLogService::class)->log($student, $staff, [
            'call_connected' => true,
            'who_answered' => 'father',
            'visit_status' => VisitStatus::Interested->value,
            'call_notes' => 'Asked to call back after two days with fee details.',
            'next_followup_at' => now()->addDays(2)->setTime(11, 0)->format('Y-m-d H:i:s'),
        ]);

        $student->refresh();

        $this->assertSame(0, $queue->dueQueueCount($staff));
        $this->assertSame(1, $queue->scheduledQueueCount($staff));

        $student->update([
            'next_call_followup_at' => now()->setTime(11, 0),
            'last_call_at' => now()->subDay(),
        ]);

        $this->assertSame(1, $queue->dueQueueCount($staff));
    }

    public function test_lead_timeline_merges_visits_and_calls_without_duplicates(): void
    {
        [$staff, $student] = $this->createAssignedLead();

        app(CallLogService::class)->log($student, $staff, [
            'call_connected' => true,
            'who_answered' => 'student',
            'visit_status' => VisitStatus::FollowUpRequired->value,
            'call_notes' => 'Will visit campus next week for counselling.',
            'next_followup_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        ]);

        $timeline = app(LeadTimelineService::class)->forStudent($student->fresh());

        $this->assertCount(1, $timeline);
        $this->assertSame('call', $timeline->first()['type']);
        $this->assertSame('Outgoing call', $timeline->first()['label']);
    }

    /**
     * @return array{0: User, 1: Student}
     */
    protected function createAssignedLead(): array
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $staff = User::factory()->create(['is_active' => true, 'name' => 'Telecaller Khushi']);
        $staff->assignRole(RoleName::Staff->value);

        $student = Student::query()->create([
            'name' => 'Rahul Sharma',
            'mobile' => '9876501234',
            'status' => StudentStatus::Enquiry,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 11',
            'code' => 'C11-'.uniqid(),
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'ENQ-'.uniqid(),
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'latest_visit_status' => VisitStatus::Interested,
        ]);

        app(LeadAssignmentService::class)->assignForCalling($enquiry, $staff, $staff);

        return [$staff, $student->fresh()];
    }
}
