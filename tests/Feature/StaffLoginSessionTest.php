<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\StaffLoginMethod;
use App\Models\StaffLoginSession;
use App\Models\User;
use App\Services\StaffLoginSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffLoginSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_opens_a_session_and_second_login_closes_the_previous(): void
    {
        $user = $this->staffUser();
        $service = app(StaffLoginSessionService::class);

        $first = Request::create('/admin/login', 'POST');
        $first->setLaravelSession($this->app['session']->driver());
        $service->recordLogin($user, $first);

        $this->assertDatabaseCount('staff_login_sessions', 1);
        $this->assertNull(StaffLoginSession::query()->first()->logged_out_at);
        $this->assertSame(StaffLoginMethod::Password, StaffLoginSession::query()->first()->method);

        $second = Request::create('/staff/otp-login/verify', 'POST');
        $second->setLaravelSession($this->app['session']->driver());
        $service->recordLogin($user, $second);

        $this->assertDatabaseCount('staff_login_sessions', 2);
        $this->assertNotNull(StaffLoginSession::query()->orderBy('id')->first()->logged_out_at);
        $this->assertSame(StaffLoginMethod::Otp, StaffLoginSession::query()->orderByDesc('id')->first()->method);
        $this->assertNull(StaffLoginSession::query()->orderByDesc('id')->first()->logged_out_at);
    }

    public function test_logout_closes_the_open_session(): void
    {
        $user = $this->staffUser();
        $service = app(StaffLoginSessionService::class);
        $request = Request::create('/admin/login', 'POST');
        $request->setLaravelSession($this->app['session']->driver());

        $service->recordLogin($user, $request);
        $service->recordLogout($user, $request);

        $session = StaffLoginSession::query()->first();
        $this->assertNotNull($session->logged_out_at);
    }

    public function test_auth_login_event_records_a_row(): void
    {
        $user = $this->staffUser();

        $this->actingAs($user);
        Auth::logout();
        Auth::login($user);

        $this->assertTrue(
            StaffLoginSession::query()->where('user_id', $user->id)->exists()
        );
    }

    public function test_platform_operator_is_not_logged(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'is_platform_operator' => true,
        ]);

        app(StaffLoginSessionService::class)->recordLogin($user);

        $this->assertDatabaseCount('staff_login_sessions', 0);
    }

    public function test_old_sessions_are_pruned(): void
    {
        $user = $this->staffUser();

        StaffLoginSession::query()->create([
            'user_id' => $user->id,
            'logged_in_at' => now()->subDays(200),
            'logged_out_at' => now()->subDays(200),
            'method' => StaffLoginMethod::Password,
        ]);
        StaffLoginSession::query()->create([
            'user_id' => $user->id,
            'logged_in_at' => now()->subDay(),
            'logged_out_at' => now(),
            'method' => StaffLoginMethod::Otp,
        ]);

        $removed = app(StaffLoginSessionService::class)->pruneOldSessions();

        $this->assertSame(1, $removed);
        $this->assertDatabaseCount('staff_login_sessions', 1);
    }

    protected function staffUser(): User
    {
        Role::findOrCreate(RoleName::Staff->value);

        $user = User::factory()->create([
            'is_active' => true,
            'is_platform_operator' => false,
        ]);
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }
}
