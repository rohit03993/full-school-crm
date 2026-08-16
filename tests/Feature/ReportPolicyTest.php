<?php

namespace Tests\Feature;

use App\Enums\ReportType;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Models\User;
use App\Policies\ReportPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_exporting_needs_a_job_role_that_grants_it(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $genericStaff = User::factory()->create(['is_active' => true]);
        $genericStaff->assignRole(RoleName::Staff->value);

        $policy = new ReportPolicy;

        $this->assertFalse($policy->export($genericStaff, ReportType::Enquiries));
        $this->assertFalse($policy->export($genericStaff, ReportType::FeeCollection));
    }

    public function test_accountant_can_export_operational_and_financial_reports(): void
    {
        $accountant = User::factory()->create(['is_active' => true]);
        $accountant->assignRole(StaffJobRole::Accountant->value);

        $policy = new ReportPolicy;

        $this->assertTrue($policy->viewAny($accountant));
        $this->assertTrue($policy->export($accountant, ReportType::Enquiries));
        $this->assertTrue($policy->export($accountant, ReportType::FeeCollection));
    }

    public function test_super_admin_can_export_everything(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $policy = new ReportPolicy;

        $this->assertTrue($policy->export($admin, ReportType::Enquiries));
        $this->assertTrue($policy->export($admin, ReportType::FeeCollection));
    }
}
