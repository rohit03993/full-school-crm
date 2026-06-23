<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\CrmPermissionSyncService;
use App\Services\StudentAuthService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FilamentAdminLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(CrmPermissionSyncService::class)->sync();
    }

    public function test_super_admin_can_authenticate_with_mobile(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'mobile' => '9811000001',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole(RoleName::SuperAdmin->value);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(\App\Filament\Auth\Login::class)
            ->fillForm([
                'login' => '9811000001',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_can_authenticate_with_mobile(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'mobile' => '9811000002',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole(StaffJobRole::Counsellor->value);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(\App\Filament\Auth\Login::class)
            ->fillForm([
                'login' => '9811000002',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_can_authenticate_with_country_code_mobile(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'mobile' => '9811000003',
            'password' => 'password',
            'is_active' => true,
        ]);
        $user->assignRole(StaffJobRole::Counsellor->value);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(\App\Filament\Auth\Login::class)
            ->fillForm([
                'login' => '+91 9811000003',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_without_panel_access_cannot_authenticate(): void
    {
        User::factory()->create([
            'email' => null,
            'mobile' => '9811000099',
            'password' => 'password',
            'is_active' => true,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(\App\Filament\Auth\Login::class)
            ->fillForm([
                'login' => '9811000099',
                'password' => 'password',
            ])
            ->call('authenticate')
            ->assertHasErrors(['data.login']);
    }
}
