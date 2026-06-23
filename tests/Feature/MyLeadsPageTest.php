<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Pages\MyLeadsPage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MyLeadsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_leads_page_renders_without_undefined_view_variables(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole(RoleName::Staff->value);

        $this->actingAs($staff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(MyLeadsPage::class)
            ->assertOk()
            ->assertSee('Assigned')
            ->assertSee('Uncalled')
            ->set('calledFilter', 'uncalled')
            ->assertOk();
    }
}
