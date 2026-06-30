<?php

namespace Tests\Feature;

use App\Enums\RoleName;
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
}
