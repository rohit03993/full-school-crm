<?php

namespace Tests\Feature;

use App\Enums\CrmPermission;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Models\User;
use App\Services\CrmPermissionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmStaffRolesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(CrmPermissionSyncService::class)->sync();
    }

    public function test_staff_can_have_multiple_job_roles_with_combined_permissions(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([
            StaffJobRole::Counsellor->value,
            StaffJobRole::Accountant->value,
        ]);

        $this->assertTrue($user->canCrm(CrmPermission::LeadsCall));
        $this->assertTrue($user->canCrm(CrmPermission::FeesCollect));
        $this->assertFalse($user->canCrm(CrmPermission::AttendanceMark));
    }

    public function test_all_job_roles_combined_cover_operations(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles(StaffJobRole::values());

        $this->assertTrue($user->canCrm(CrmPermission::LeadsCall));
        $this->assertTrue($user->canCrm(CrmPermission::FeesCollect));
        $this->assertTrue($user->canCrm(CrmPermission::FeesAdjustStructure));
        $this->assertTrue($user->canCrm(CrmPermission::AttendanceMark));
        $this->assertTrue($user->canCrm(CrmPermission::WhatsappCampaigns));
        $this->assertFalse($user->canCrm(CrmPermission::SettingsManage));
    }

    public function test_super_admin_bypasses_all_permission_checks(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        $this->assertTrue($user->canCrm(CrmPermission::SettingsManage));
        $this->assertTrue($user->canCrm(CrmPermission::StaffManage));
    }

    public function test_panel_access_with_job_roles_only(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(StaffJobRole::Counsellor->value);

        $this->assertTrue(\App\Support\CrmAccess::hasPanelAccess($user));
    }

    public function test_counsellor_cannot_collect_fees(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(StaffJobRole::Counsellor->value);

        $this->assertFalse($user->canCrm(CrmPermission::FeesCollect));
        $this->assertFalse($user->can('create', \App\Models\Payment::class));
    }

    public function test_accountant_can_collect_fees(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(StaffJobRole::Accountant->value);

        $this->assertTrue($user->canCrm(CrmPermission::FeesCollect));
        $this->assertTrue($user->can('create', \App\Models\Payment::class));
        $this->assertFalse($user->canCrm(CrmPermission::FeesAdjustStructure));
    }

    public function test_fee_adjuster_can_adjust_structure_but_not_collect_by_default(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(StaffJobRole::FeeAdjuster->value);

        $this->assertTrue($user->canCrm(CrmPermission::FeesAdjustStructure));
        $this->assertTrue($user->canCrm(CrmPermission::FeesWaivePenalty));
        $this->assertTrue(\App\Support\CrmAccess::canViewFees($user));
        $this->assertTrue($user->can('update', new \App\Models\Payment));
        $this->assertFalse($user->canCrm(CrmPermission::FeesCollect));
        $this->assertFalse($user->can('create', \App\Models\Payment::class));
    }

    public function test_accountant_plus_fee_adjuster_can_collect_and_adjust(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->syncRoles([
            StaffJobRole::Accountant->value,
            StaffJobRole::FeeAdjuster->value,
        ]);

        $this->assertTrue($user->canCrm(CrmPermission::FeesCollect));
        $this->assertTrue($user->canCrm(CrmPermission::FeesAdjustStructure));
        $this->assertTrue($user->can('create', \App\Models\Payment::class));
        $this->assertTrue($user->can('update', new \App\Models\Payment));
    }

    public function test_admission_officer_can_approve_admissions(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(StaffJobRole::AdmissionOfficer->value);

        $this->assertTrue($user->canCrm(CrmPermission::AdmissionsApprove));
        $this->assertFalse($user->canCrm(CrmPermission::FeesCollect));
    }

    public function test_legacy_staff_role_keeps_operational_permissions(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::Staff->value);

        $this->assertFalse($user->canCrm(CrmPermission::FeesCollect));
        $this->assertTrue($user->canCrm(CrmPermission::LeadsCall));
        $this->assertFalse($user->canCrm(CrmPermission::CasesViewAll));
        $this->assertFalse($user->canCrm(CrmPermission::AttendanceMark));
        $this->assertFalse($user->canCrm(CrmPermission::MarksImport));
        $this->assertFalse($user->canCrm(CrmPermission::WhatsappCampaigns));
        $this->assertFalse($user->canCrm(CrmPermission::SettingsManage));
    }

    public function test_counsellor_cannot_view_fees_tab_on_student_profile(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(StaffJobRole::Counsellor->value);

        $this->assertFalse(\App\Support\CrmAccess::canViewFees($user));
    }

    public function test_accountant_can_view_fees_tab_on_student_profile(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(StaffJobRole::Accountant->value);

        $this->assertTrue(\App\Support\CrmAccess::canViewFees($user));
    }

    public function test_counsellor_cannot_access_tests_or_messaging_menus(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(StaffJobRole::Counsellor->value);

        $this->actingAs($user);

        $this->assertFalse($user->canCrm(CrmPermission::MarksImport));
        $this->assertFalse($user->canCrm(CrmPermission::WhatsappCampaigns));
        $this->assertFalse(\App\Filament\Resources\ActivitySessions\ActivitySessionResource::canAccess());
        $this->assertFalse(\App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource::canAccess());
    }

    public function test_academic_coordinator_can_access_tests_not_messaging(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(StaffJobRole::AcademicCoordinator->value);

        $this->actingAs($user);

        $this->assertTrue($user->canCrm(CrmPermission::MarksImport));
        $this->assertFalse($user->canCrm(CrmPermission::WhatsappCampaigns));
        $this->assertTrue(\App\Filament\Resources\ActivitySessions\ActivitySessionResource::canAccess());
        $this->assertFalse(\App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource::canAccess());
    }

    public function test_messaging_coordinator_can_send_whatsapp_not_enter_marks(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(StaffJobRole::MessagingCoordinator->value);

        $this->actingAs($user);

        $this->assertTrue($user->canCrm(CrmPermission::WhatsappCampaigns));
        $this->assertFalse($user->canCrm(CrmPermission::MarksImport));
        $this->assertFalse(\App\Filament\Resources\ActivitySessions\ActivitySessionResource::canAccess());
        $this->assertTrue(\App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource::canAccess());
    }

    public function test_every_job_role_can_access_my_work_and_own_cases(): void
    {
        foreach (StaffJobRole::cases() as $role) {
            $user = User::factory()->create(['is_active' => true]);
            $user->assignRole($role->value);
            $this->actingAs($user);

            $this->assertTrue(
                $user->canCrm(CrmPermission::CasesView),
                "{$role->value} should view own cases",
            );
            $this->assertTrue(
                \App\Filament\Pages\MyMeetingsPage::canAccess(),
                "{$role->value} should access My work",
            );

            if ($role === StaffJobRole::AdmissionOfficer) {
                $this->assertTrue($user->canCrm(CrmPermission::CasesViewAll));
            } else {
                $this->assertFalse(
                    $user->canCrm(CrmPermission::CasesViewAll),
                    "{$role->value} should not view all institute cases",
                );
            }
        }
    }
}
