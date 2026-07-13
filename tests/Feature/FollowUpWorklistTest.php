<?php

namespace Tests\Feature;

use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\VisitStatus;
use App\Models\Student;
use App\Models\User;
use App\Models\Visit;
use App\Services\CrmPermissionSyncService;
use App\Services\EnquiryService;
use App\Services\FollowUpWorklistService;
use App\Services\LeadAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FollowUpWorklistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(CrmPermissionSyncService::class)->sync();
    }

    public function test_due_and_overdue_includes_past_and_today_follow_ups(): void
    {
        $staff = $this->createCounsellorUser();
        $admin = $this->createSuperAdminUser();
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
            'staff_user_id' => $staff->id,
            'discussion_summary' => 'Overdue follow-up visit.',
            'next_follow_up_date' => today()->subDay(),
            'status' => VisitStatus::FollowUpRequired,
        ]);

        Visit::query()->create([
            'student_id' => $enquiry->student_id,
            'enquiry_id' => $enquiry->id,
            'visit_date' => today(),
            'staff_user_id' => $staff->id,
            'discussion_summary' => 'Due today visit.',
            'next_follow_up_date' => today(),
            'status' => VisitStatus::FollowUpRequired,
        ]);

        Visit::query()->create([
            'student_id' => $enquiry->student_id,
            'enquiry_id' => $enquiry->id,
            'visit_date' => today(),
            'staff_user_id' => $staff->id,
            'discussion_summary' => 'Upcoming visit.',
            'next_follow_up_date' => today()->addDays(3),
            'status' => VisitStatus::FollowUpRequired,
        ]);

        $worklist = app(FollowUpWorklistService::class);

        $this->assertCount(2, $worklist->dueAndOverdue($admin));
        $this->assertSame(2, $worklist->dueCount($admin));
        $this->assertCount(1, $worklist->upcoming($admin));
    }

    public function test_call_follow_ups_are_included_in_worklist(): void
    {
        $staff = $this->createCounsellorUser();
        $admin = $this->createSuperAdminUser();

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Call Follow Up Student',
            'mobile' => '9000000003',
            'discussion_summary' => 'Needs callback.',
            'visit_status' => VisitStatus::FollowUpRequired->value,
        ], $staff, LeadSource::WalkIn);

        app(LeadAssignmentService::class)->assignForCalling($enquiry, $staff, $admin);

        Student::query()->whereKey($enquiry->student_id)->update([
            'next_call_followup_at' => today()->subDay(),
            'total_calls' => 1,
        ]);

        $worklist = app(FollowUpWorklistService::class);

        $this->assertCount(1, $worklist->dueCallFollowUps($admin));
        $this->assertSame(1, $worklist->dueCallFollowUpCount($admin));
        $this->assertSame(1, $worklist->totalDueCount($admin));
        $this->assertCount(1, $worklist->dueCallFollowUps($staff));
    }

    public function test_visits_without_follow_up_date_are_excluded(): void
    {
        $staff = $this->createCounsellorUser();
        $admin = $this->createSuperAdminUser();

        app(EnquiryService::class)->create([
            'name' => 'No Follow Up',
            'mobile' => '9000000002',
            'discussion_summary' => 'No follow-up scheduled.',
            'visit_status' => VisitStatus::Interested->value,
        ], $staff, LeadSource::WalkIn);

        $worklist = app(FollowUpWorklistService::class);

        $this->assertCount(0, $worklist->dueAndOverdue($admin));
        $this->assertSame(0, $worklist->dueCount($admin));
    }

    public function test_phone_call_mirror_visits_are_excluded_from_visit_follow_ups(): void
    {
        $staff = $this->createCounsellorUser();
        $admin = $this->createSuperAdminUser();

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Phone Mirror Student',
            'mobile' => '9000000004',
            'discussion_summary' => 'Initial walk-in.',
            'visit_status' => VisitStatus::FollowUpRequired->value,
        ], $staff, LeadSource::WalkIn);

        app(LeadAssignmentService::class)->assignForCalling($enquiry, $staff, $admin);

        Visit::query()->create([
            'student_id' => $enquiry->student_id,
            'enquiry_id' => $enquiry->id,
            'visit_date' => today(),
            'staff_user_id' => $staff->id,
            'discussion_summary' => 'Callback notes from telecaller.',
            'remarks' => 'Outgoing call',
            'next_follow_up_date' => today()->addDay(),
            'status' => VisitStatus::FollowUpRequired,
        ]);

        Student::query()->whereKey($enquiry->student_id)->update([
            'next_call_followup_at' => today()->addDay(),
        ]);

        $worklist = app(FollowUpWorklistService::class);

        $this->assertCount(0, $worklist->upcoming($admin));
        $this->assertCount(1, $worklist->upcomingCallFollowUps($admin));
    }

    public function test_staff_only_sees_own_visit_follow_ups(): void
    {
        $staffA = $this->createCounsellorUser('Staff A');
        $staffB = $this->createCounsellorUser('Staff B');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Visit Follow Up',
            'mobile' => '9000000101',
            'discussion_summary' => 'Needs visit follow-up.',
            'visit_status' => VisitStatus::FollowUpRequired->value,
        ], $staffA, LeadSource::WalkIn);

        Visit::query()->create([
            'student_id' => $enquiry->student_id,
            'enquiry_id' => $enquiry->id,
            'visit_date' => today(),
            'staff_user_id' => $staffA->id,
            'discussion_summary' => 'Follow-up due today.',
            'next_follow_up_date' => today(),
            'status' => VisitStatus::FollowUpRequired,
        ]);

        $worklist = app(FollowUpWorklistService::class);

        $this->assertCount(1, $worklist->dueAndOverdue($staffA));
        $this->assertCount(0, $worklist->dueAndOverdue($staffB));
    }

    public function test_staff_only_sees_assigned_call_follow_ups(): void
    {
        $admin = $this->createSuperAdminUser();
        $staffA = $this->createCounsellorUser('Staff A');
        $staffB = $this->createCounsellorUser('Staff B');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Assigned Callback',
            'mobile' => '9000000102',
            'discussion_summary' => 'Callback assigned to staff A.',
            'visit_status' => VisitStatus::FollowUpRequired->value,
        ], $staffA, LeadSource::WalkIn);

        app(LeadAssignmentService::class)->assignForCalling($enquiry, $staffA, $admin);

        Student::query()->whereKey($enquiry->student_id)->update([
            'next_call_followup_at' => today(),
            'total_calls' => 1,
        ]);

        $worklist = app(FollowUpWorklistService::class);

        $this->assertCount(1, $worklist->dueCallFollowUps($staffA));
        $this->assertCount(0, $worklist->dueCallFollowUps($staffB));
        $this->assertCount(1, $worklist->dueCallFollowUps($admin));
        $this->assertSame(1, $worklist->totalDueCount($staffA));
        $this->assertSame(0, $worklist->totalDueCount($staffB));
    }

    protected function createCounsellorUser(string $name = 'Counsellor'): User
    {
        $user = User::factory()->create(['is_active' => true, 'name' => $name]);
        $user->assignRole(StaffJobRole::Counsellor->value);

        return $user;
    }

    protected function createSuperAdminUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true, 'name' => 'Super Admin']);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }
}
