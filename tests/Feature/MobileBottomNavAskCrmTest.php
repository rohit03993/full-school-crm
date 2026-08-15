<?php

namespace Tests\Feature;

use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Enums\RoleName;
use App\Filament\Pages\Dashboard;
use App\Livewire\AskCrmChatWidget;
use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseService;
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
            ->assertSee("Livewire.dispatch('ask-crm-toggle')", false)
            // The floating pill is CSS-hidden below lg so it can never cover a tab
            ->assertSee('crm-ask__launcher', false);
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
