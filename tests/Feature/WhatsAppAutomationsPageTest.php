<?php

namespace Tests\Feature;

use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Enums\RoleName;
use App\Filament\Pages\ManageWhatsAppSettings;
use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppAutomationsPageTest extends TestCase
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

    public function test_automations_page_shows_student_and_staff_tabs(): void
    {
        $role = Role::findByName(RoleName::SuperAdmin->value);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole($role);

        $this->actingAs($admin);

        Livewire::test(ManageWhatsAppSettings::class)
            ->assertOk()
            ->assertSee('Student attendance')
            ->assertSee('Staff attendance')
            ->assertSee('Fee reminders')
            ->assertSee('Homework not done')
            ->assertSee('WhatsApp to parents')
            ->assertDontSee('Parents attendance');
    }
}
