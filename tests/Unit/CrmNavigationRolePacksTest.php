<?php

namespace Tests\Unit;

use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Models\User;
use App\Support\CrmNavigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CrmNavigationRolePacksTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_is_owner_only(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);
        $admin->assignRole(StaffJobRole::Counsellor->value);

        $this->assertSame(['owner'], CrmNavigation::navRolePacks($admin));
        $this->assertSame('owner', CrmNavigation::navRolePack($admin));
    }

    public function test_multi_role_returns_union_of_packs(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(StaffJobRole::Accountant->value);
        $user->assignRole(StaffJobRole::Counsellor->value);
        $user->assignRole(StaffJobRole::Teacher->value);

        $packs = CrmNavigation::navRolePacks($user);

        $this->assertSame(['finance', 'calling', 'academic'], $packs);
        $this->assertSame('finance', CrmNavigation::navRolePack($user));
    }

    public function test_admission_officer_gets_admissions_pack(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(StaffJobRole::AdmissionOfficer->value);

        $this->assertSame(['admissions'], CrmNavigation::navRolePacks($user));
    }

    public function test_legacy_staff_without_job_role_gets_default(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::Staff->value);

        $this->assertSame(['default'], CrmNavigation::navRolePacks($user));
    }
}
