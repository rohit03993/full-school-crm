<?php

namespace Tests\Feature;

use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\VisitStatus;
use App\Models\Student;
use App\Models\User;
use App\Models\Visit;
use App\Services\EnquiryService;
use App\Services\FollowUpWorklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FollowUpWorklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_and_overdue_includes_past_and_today_follow_ups(): void
    {
        $staff = $this->createStaffUser();
        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Follow Up Student',
            'mobile' => '9000000001',
            'discussion_summary' => 'Initial visit.',
            'visit_status' => VisitStatus::FollowUpRequired->value,
        ], $staff, LeadSource::WalkIn);

        Visit::query()->create([
            'student_id' => $enquiry->student_id,
            'enquiry_id' => $enquiry->id,
            'visit_date' => today()->subDays(3),
            'discussion_summary' => 'Overdue follow-up visit.',
            'next_follow_up_date' => today()->subDay(),
            'status' => VisitStatus::FollowUpRequired,
        ]);

        Visit::query()->create([
            'student_id' => $enquiry->student_id,
            'enquiry_id' => $enquiry->id,
            'visit_date' => today(),
            'discussion_summary' => 'Due today visit.',
            'next_follow_up_date' => today(),
            'status' => VisitStatus::FollowUpRequired,
        ]);

        Visit::query()->create([
            'student_id' => $enquiry->student_id,
            'enquiry_id' => $enquiry->id,
            'visit_date' => today(),
            'discussion_summary' => 'Upcoming visit.',
            'next_follow_up_date' => today()->addDays(3),
            'status' => VisitStatus::FollowUpRequired,
        ]);

        $worklist = app(FollowUpWorklistService::class);

        $this->assertCount(2, $worklist->dueAndOverdue());
        $this->assertSame(2, $worklist->dueCount());
        $this->assertCount(1, $worklist->upcoming());
    }

    public function test_call_follow_ups_are_included_in_worklist(): void
    {
        $staff = $this->createStaffUser();

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Call Follow Up Student',
            'mobile' => '9000000003',
            'discussion_summary' => 'Needs callback.',
            'visit_status' => VisitStatus::FollowUpRequired->value,
        ], $staff, LeadSource::WalkIn);

        Student::query()->whereKey($enquiry->student_id)->update([
            'next_call_followup_at' => today()->subDay(),
            'total_calls' => 1,
        ]);

        $worklist = app(FollowUpWorklistService::class);

        $this->assertCount(1, $worklist->dueCallFollowUps());
        $this->assertSame(1, $worklist->dueCallFollowUpCount());
        $this->assertSame(1, $worklist->totalDueCount());
    }

    public function test_visits_without_follow_up_date_are_excluded(): void
    {
        $staff = $this->createStaffUser();

        app(EnquiryService::class)->create([
            'name' => 'No Follow Up',
            'mobile' => '9000000002',
            'discussion_summary' => 'No follow-up scheduled.',
            'visit_status' => VisitStatus::Interested->value,
        ], $staff, LeadSource::WalkIn);

        $worklist = app(FollowUpWorklistService::class);

        $this->assertCount(0, $worklist->dueAndOverdue());
        $this->assertSame(0, $worklist->dueCount());
    }

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }
}
