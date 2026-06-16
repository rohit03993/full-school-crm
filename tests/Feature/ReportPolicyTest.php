<?php

namespace Tests\Feature;

use App\Enums\ReportType;
use App\Enums\RoleName;
use App\Models\User;
use App\Policies\ReportPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_export_operational_reports_only(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole(RoleName::Staff->value);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $policy = new ReportPolicy;

        $this->assertTrue($policy->export($staff, ReportType::Enquiries));
        $this->assertFalse($policy->export($staff, ReportType::FeeCollection));

        $this->assertTrue($policy->export($admin, ReportType::Enquiries));
        $this->assertTrue($policy->export($admin, ReportType::FeeCollection));
    }
}
