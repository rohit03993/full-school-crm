<?php

namespace Tests\Feature;

use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Enums\RoleName;
use App\Filament\Pages\AllCasesPage;
use App\Filament\Pages\AttendanceHubPage;
use App\Filament\Pages\AttendancePage;
use App\Filament\Pages\FeesDashboardPage;
use App\Filament\Pages\FeesHubPage;
use App\Filament\Pages\SetupHubPage;
use App\Filament\Pages\StaffAttendancePage;
use App\Filament\Pages\WhatsAppHubPage;
use App\Filament\Pages\WhatsAppInboxPage;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseService;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class NavigationHubsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleName::SuperAdmin->value);

        Setting::query()->whereIn('key', [
            LicenseService::PAYLOAD_KEY,
            LicenseService::SIGNATURE_KEY,
        ])->delete();
        Setting::flushValueCache();

        app(LicenseService::class)->save([
            'plan' => LicensePlan::Custom->value,
            'features' => array_map(
                fn (LicenseFeature $feature): string => $feature->value,
                LicenseFeature::cases(),
            ),
            'expires_at' => now()->addYear()->toDateString(),
        ]);
    }

    public function test_hubs_register_while_leaf_pages_stay_hidden(): void
    {
        $this->assertTrue(AttendanceHubPage::shouldRegisterNavigation());
        $this->assertFalse(AttendancePage::shouldRegisterNavigation());
        $this->assertFalse(StaffAttendancePage::shouldRegisterNavigation());

        $this->assertTrue(FeesHubPage::shouldRegisterNavigation());
        $this->assertFalse(FeesDashboardPage::shouldRegisterNavigation());

        $this->assertTrue(WhatsAppHubPage::shouldRegisterNavigation());
        $this->assertFalse(WhatsAppInboxPage::shouldRegisterNavigation());
        $this->assertFalse(WhatsAppCampaignResource::shouldRegisterNavigation());

        $this->assertTrue(SetupHubPage::shouldRegisterNavigation());
        $this->assertFalse(AllCasesPage::shouldRegisterNavigation());
    }

    public function test_student_call_log_label_and_setup_whatsapp_groups_start_collapsed(): void
    {
        $this->assertSame('Student call log', CrmMenuLabels::studentCalls());
        $this->assertTrue(CrmNavigation::groupStartsCollapsed(CrmNavigation::GROUP_SETTINGS));
        $this->assertTrue(CrmNavigation::groupStartsCollapsed(CrmNavigation::GROUP_META_WHATSAPP));
        $this->assertTrue(CrmNavigation::groupStartsCollapsed(CrmNavigation::GROUP_ADMIN));
    }

    public function test_disabled_whatsapp_module_hides_whatsapp_hub_only(): void
    {
        app(LicenseService::class)->save([
            'plan' => LicensePlan::Custom->value,
            'features' => [
                LicenseFeature::Attendance->value,
                LicenseFeature::Fees->value,
            ],
            'expires_at' => now()->addYear()->toDateString(),
        ]);

        $this->assertFalse(FeatureGate::enabled(LicenseFeature::WhatsApp));
        $this->assertFalse(WhatsAppHubPage::canAccess());
        $this->assertTrue(FeatureGate::enabled(LicenseFeature::Attendance));
        $this->assertTrue(FeatureGate::enabled(LicenseFeature::Fees));
    }

    public function test_setup_hub_is_super_admin_only(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);
        $this->actingAs($admin);

        $this->assertTrue(SetupHubPage::canAccess());
    }

    public function test_disabled_attendance_hides_attendance_hub_not_fees(): void
    {
        app(LicenseService::class)->save([
            'plan' => LicensePlan::Custom->value,
            'features' => [
                LicenseFeature::Fees->value,
                LicenseFeature::WhatsApp->value,
            ],
            'expires_at' => now()->addYear()->toDateString(),
        ]);

        $this->assertFalse(AttendanceHubPage::canAccess());
        $this->assertTrue(FeatureGate::enabled(LicenseFeature::Fees));
        $this->assertTrue(FeatureGate::enabled(LicenseFeature::WhatsApp));
    }
}
