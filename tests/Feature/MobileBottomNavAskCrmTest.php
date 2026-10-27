<?php

namespace Tests\Feature;

use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Filament\Pages\Dashboard;
use App\Livewire\AskCrmChatWidget;
use App\Models\Setting;
use App\Models\User;
use App\Services\CrmPermissionSyncService;
use App\Services\LicenseService;
use App\Support\CrmMobileBottomNav;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MobileBottomNavAskCrmTest extends TestCase
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

        Setting::setValue('site.name', 'Test Institute', 'general');
        Setting::setValue('crm.onboarding_completed', '1', 'crm');

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);
        $this->actingAs($admin);
    }

    public function test_bottom_nav_offers_ask_crm_instead_of_a_pill_over_the_tabs(): void
    {
        $this->get(Dashboard::getUrl())
            ->assertOk()
            ->assertSee('fi-mobile-bottom-nav', false)
            ->assertDontSee("Livewire.dispatch('ask-crm-toggle')", false)
            ->assertDontSee('crm-ask__launcher', false)
            ->assertDontSee('>Ask CRM<', false);
    }

    public function test_super_admin_gets_cases_and_callers_keep_calls(): void
    {
        app(CrmPermissionSyncService::class)->sync();

        $adminLabels = array_column(CrmMobileBottomNav::tabs(auth()->user(), 'admin'), 'label');
        $this->assertContains('Cases', $adminLabels);
        $this->assertNotContains('Calls', $adminLabels);

        $counsellor = User::factory()->create(['is_active' => true]);
        $counsellor->assignRole(StaffJobRole::Counsellor->value);
        $this->actingAs($counsellor);

        $callerLabels = array_column(CrmMobileBottomNav::tabs($counsellor, 'admin'), 'label');
        $this->assertContains('Calls', $callerLabels);
        $this->assertNotContains('Cases', $callerLabels);

        $accountant = User::factory()->create(['is_active' => true]);
        $accountant->assignRole(StaffJobRole::Accountant->value);
        $this->actingAs($accountant);

        $otherLabels = array_column(CrmMobileBottomNav::tabs($accountant, 'admin'), 'label');
        $this->assertContains('Cases', $otherLabels);
        $this->assertNotContains('Calls', $otherLabels);
    }

    public function test_nav_button_event_opens_and_closes_the_chat(): void
    {
        Livewire::test(AskCrmChatWidget::class)
            ->assertSet('open', false)
            ->dispatch('ask-crm-toggle')
            ->assertSet('open', true)
            ->dispatch('ask-crm-toggle')
            ->assertSet('open', false);
    }
}
