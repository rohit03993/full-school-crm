<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Pages\AccountingLedgerPage;
use App\Filament\Pages\FeesDashboardPage;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeesDashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_fees_dashboard_page_loads_summary_for_super_admin(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        Setting::setValue('site.name', 'Test Institute', 'general');
        Setting::setValue('crm.onboarding_completed', '1', 'crm');

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(FeesDashboardPage::class)
            ->assertOk()
            ->assertSet('summary.collection_today', 0.0)
            ->call('refreshDashboard')
            ->assertOk();
    }

    public function test_fees_page_can_switch_to_ledger_tab(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        Setting::setValue('site.name', 'Test Institute', 'general');
        Setting::setValue('crm.onboarding_completed', '1', 'crm');

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(FeesDashboardPage::class)
            ->assertSet('activeTab', 'overview')
            ->call('setActiveTab', 'ledger')
            ->assertSet('activeTab', 'ledger')
            ->assertSet('ledgerSummary.total_collected', 0.0);
    }

    public function test_fees_page_opens_ledger_tab_from_query_string(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        Setting::setValue('site.name', 'Test Institute', 'general');
        Setting::setValue('crm.onboarding_completed', '1', 'crm');

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->get(FeesDashboardPage::getUrl(['tab' => 'ledger']))
            ->assertOk();
    }

    public function test_legacy_accounting_ledger_url_redirects_to_fees_ledger_tab(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        Setting::setValue('site.name', 'Test Institute', 'general');
        Setting::setValue('crm.onboarding_completed', '1', 'crm');

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(AccountingLedgerPage::class)
            ->assertRedirect(FeesDashboardPage::getUrl(['tab' => 'ledger']));
    }
}
