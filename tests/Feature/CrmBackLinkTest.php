<?php

namespace Tests\Feature;

use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Enums\RoleName;
use App\Filament\Pages\AttendanceHubPage;
use App\Filament\Pages\AttendancePage;
use App\Filament\Pages\BackupsPage;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\SetupHubPage;
use App\Filament\Pages\WhatsAppHubPage;
use App\Filament\Pages\WhatsAppInboxPage;
use App\Filament\Resources\WhatsAppCampaigns\Pages\ListWhatsAppCampaigns;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseService;
use App\Support\CrmBackLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CrmBackLinkTest extends TestCase
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

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);
        $this->actingAs($admin);
    }

    public function test_dashboard_has_no_back_link(): void
    {
        $this->assertNull(CrmBackLink::forScopes([Dashboard::class]));
        $this->assertNull(CrmBackLink::forScopes([]));
    }

    public function test_leaf_pages_point_at_their_hub(): void
    {
        $this->assertSame(
            AttendanceHubPage::getUrl(),
            CrmBackLink::forScopes([AttendancePage::class])['url'],
        );

        $this->assertSame(
            WhatsAppHubPage::getUrl(),
            CrmBackLink::forScopes([WhatsAppInboxPage::class])['url'],
        );

        $setupBack = CrmBackLink::forScopes([BackupsPage::class]);
        $this->assertSame(SetupHubPage::getUrl(), $setupBack['url']);
        $this->assertSame('Setup', $setupBack['label']);
    }

    public function test_resource_list_page_points_at_hub_and_child_page_points_at_list(): void
    {
        $this->assertSame(
            WhatsAppHubPage::getUrl(),
            CrmBackLink::forScopes([ListWhatsAppCampaigns::class, WhatsAppCampaignResource::class])['url'],
        );

        $this->assertSame(
            WhatsAppCampaignResource::getUrl('index'),
            CrmBackLink::forScopes([
                WhatsAppCampaignResource::getPages()['create']->getPage(),
                WhatsAppCampaignResource::class,
            ])['url'],
        );
    }

    public function test_back_control_renders_on_a_page_but_not_on_the_dashboard(): void
    {
        Setting::setValue('site.name', 'Test Institute', 'general');
        Setting::setValue('crm.onboarding_completed', '1', 'crm');

        $this->get(AttendanceHubPage::getUrl())
            ->assertOk()
            ->assertSee('fi-crm-back__link', false)
            ->assertSee('to Dashboard', false);

        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertDontSee('fi-crm-back__link', false);
    }

    public function test_back_link_falls_back_to_dashboard_when_hub_module_is_off(): void
    {
        app(LicenseService::class)->save([
            'plan' => LicensePlan::Custom->value,
            'features' => [LicenseFeature::Attendance->value],
            'expires_at' => now()->addYear()->toDateString(),
        ]);

        $back = CrmBackLink::forScopes([WhatsAppInboxPage::class]);

        $this->assertSame(Dashboard::getUrl(), $back['url']);
    }
}
