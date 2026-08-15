<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Setting;
use App\Models\User;
use App\Support\SiteContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PwaAppLaunchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('site.name', 'Motion Education', 'general');
        Setting::setValue('crm.onboarding_completed', '1', 'crm');
        SiteContent::clearCache();
    }

    public function test_guest_sees_the_login_chooser(): void
    {
        $this->get(route('pwa.app'))
            ->assertOk()
            ->assertSee('Motion Education')
            ->assertSee('Staff / Admin')
            ->assertSee('Parent / Student')
            ->assertSee(url('/admin/login'), false)
            ->assertSee(route('portal.login'), false);
    }

    public function test_signed_in_staff_go_straight_to_admin(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $this->actingAs($admin)
            ->get(route('pwa.app'))
            ->assertRedirect('/admin');
    }

    public function test_portal_session_goes_straight_to_the_student_dashboard(): void
    {
        $this->withSession(['student_portal_id' => 99])
            ->get(route('pwa.app'))
            ->assertRedirect(route('portal.dashboard'));
    }

    public function test_staff_session_wins_over_a_stale_portal_session(): void
    {
        // Prefer the CRM when both somehow exist — staff opened the app.
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $this->actingAs($admin)
            ->withSession(['student_portal_id' => 99])
            ->get(route('pwa.app'))
            ->assertRedirect('/admin');
    }
}
