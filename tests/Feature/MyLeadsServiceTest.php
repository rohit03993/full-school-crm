<?php

namespace Tests\Feature;

use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\VisitStatus;
use App\Models\Student;
use App\Models\User;
use App\Services\EnquiryService;
use App\Services\LeadAssignmentService;
use App\Services\MyLeadsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MyLeadsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_leads_returns_only_admin_assigned_enquiries(): void
    {
        $admin = $this->createStaffUser('Admin');
        $staffA = $this->createStaffUser('Staff A');
        $staffB = $this->createStaffUser('Staff B');

        $assigned = app(EnquiryService::class)->create([
            'name' => 'Assigned Lead',
            'mobile' => '9000000101',
            'discussion_summary' => 'Assigned to staff A.',
            'visit_status' => VisitStatus::Interested->value,
        ], $staffA, LeadSource::WalkIn);

        $other = app(EnquiryService::class)->create([
            'name' => 'Other Lead',
            'mobile' => '9000000102',
            'discussion_summary' => 'Assigned to staff B.',
            'visit_status' => VisitStatus::Interested->value,
        ], $staffB, LeadSource::WalkIn);

        app(LeadAssignmentService::class)->assignForCalling($assigned, $staffA, $admin);
        app(LeadAssignmentService::class)->assignForCalling($other, $staffB, $admin);

        $service = app(MyLeadsService::class);
        $leads = $service->leads($staffA);

        $this->assertCount(1, $leads);
        $this->assertSame($assigned->id, $leads->first()->id);
        $this->assertSame(1, $service->stats($staffA)['total']);
    }

    public function test_meeting_with_without_admin_assignment_is_excluded(): void
    {
        $staff = $this->createStaffUser();

        app(EnquiryService::class)->create([
            'name' => 'Auto Staff Lead',
            'mobile' => '9000000103',
            'discussion_summary' => 'Staff field only — not assigned.',
            'visit_status' => VisitStatus::Interested->value,
            'meeting_with_user_id' => $staff->id,
        ], $staff, LeadSource::WalkIn);

        $service = app(MyLeadsService::class);

        $this->assertCount(0, $service->leads($staff));
        $this->assertSame(0, $service->stats($staff)['total']);
    }

    public function test_called_filter_limits_by_total_calls(): void
    {
        $admin = $this->createStaffUser('Admin');
        $staff = $this->createStaffUser();

        $uncalled = app(EnquiryService::class)->create([
            'name' => 'Uncalled Lead',
            'mobile' => '9000000201',
            'discussion_summary' => 'Never called.',
            'visit_status' => VisitStatus::Interested->value,
        ], $staff, LeadSource::WalkIn);

        $calledEnquiry = app(EnquiryService::class)->create([
            'name' => 'Called Lead',
            'mobile' => '9000000202',
            'discussion_summary' => 'Already called.',
            'visit_status' => VisitStatus::Interested->value,
        ], $staff, LeadSource::WalkIn);

        app(LeadAssignmentService::class)->assignForCalling($uncalled, $staff, $admin);
        app(LeadAssignmentService::class)->assignForCalling($calledEnquiry, $staff, $admin);

        Student::query()->whereKey($calledEnquiry->student_id)->update(['total_calls' => 2]);

        $service = app(MyLeadsService::class);

        $this->assertCount(1, $service->leads($staff, calledFilter: 'uncalled'));
        $this->assertSame($uncalled->id, $service->leads($staff, calledFilter: 'uncalled')->first()->id);
        $this->assertCount(1, $service->leads($staff, calledFilter: 'called'));
        $this->assertSame(1, $service->stats($staff)['uncalled']);
        $this->assertSame(1, $service->stats($staff)['called']);
    }

    public function test_bulk_assign_many_for_calling(): void
    {
        $admin = $this->createStaffUser('Admin');
        $staff = $this->createStaffUser('Telecaller');

        $first = app(EnquiryService::class)->create([
            'name' => 'Bulk One',
            'mobile' => '9000000801',
            'visit_status' => VisitStatus::Interested->value,
        ], $admin, LeadSource::WalkIn);

        $second = app(EnquiryService::class)->create([
            'name' => 'Bulk Two',
            'mobile' => '9000000802',
            'visit_status' => VisitStatus::Interested->value,
        ], $admin, LeadSource::WalkIn);

        $count = app(LeadAssignmentService::class)->assignManyForCalling([$first, $second], $staff, $admin);

        $this->assertSame(2, $count);
        $this->assertSame(2, app(MyLeadsService::class)->stats($staff)['total']);
    }

    public function test_admin_assigned_enrolled_lead_appears_in_assigned_list(): void
    {
        $admin = $this->createStaffUser('Admin');
        $staff = $this->createStaffUser('Khushi');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Enrolled Assigned',
            'mobile' => '9000000901',
            'visit_status' => VisitStatus::Joined->value,
        ], $admin, LeadSource::BulkImport);

        app(LeadAssignmentService::class)->assignForCalling($enquiry, $staff, $admin);

        $service = app(MyLeadsService::class);

        $this->assertSame(1, $service->stats($staff)['total']);
        $this->assertCount(1, $service->leads($staff));
    }

    protected function createStaffUser(string $name = 'Staff User'): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => $name, 'is_active' => true]);
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }
}
