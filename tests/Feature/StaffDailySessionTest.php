<?php

namespace Tests\Feature;

use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Enums\RoleName;
use App\Enums\StaffLoginMethod;
use App\Filament\Pages\Dashboard;
use App\Models\Setting;
use App\Models\StaffLoginSession;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\StaffDailySessionService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffDailySessionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_cutoff_before_8pm_is_today_and_after_8pm_is_tomorrow(): void
    {
        $service = app(StaffDailySessionService::class);

        Carbon::setTestNow(Carbon::parse('2026-09-21 10:00:00', 'Asia/Kolkata'));
        $this->assertSame('2026-09-21 20:00:00', $service->nextLogoutAt()->format('Y-m-d H:i:s'));

        Carbon::setTestNow(Carbon::parse('2026-09-21 20:00:00', 'Asia/Kolkata'));
        $this->assertSame('2026-09-22 20:00:00', $service->nextLogoutAt()->format('Y-m-d H:i:s'));

        Carbon::setTestNow(Carbon::parse('2026-09-21 21:15:00', 'Asia/Kolkata'));
        $this->assertSame('2026-09-22 20:00:00', $service->nextLogoutAt()->format('Y-m-d H:i:s'));
    }

    public function test_login_stores_daily_logout_time_on_the_session(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-21 11:00:00', 'Asia/Kolkata'));

        $user = $this->staffUser();
        Auth::login($user);

        $this->assertNotEmpty(session(StaffDailySessionService::SESSION_KEY));
        $this->assertSame(
            '2026-09-21 20:00:00',
            Carbon::parse((string) session(StaffDailySessionService::SESSION_KEY))
                ->timezone('Asia/Kolkata')
                ->format('Y-m-d H:i:s'),
        );
        $this->assertSame(
            app(StaffDailySessionService::class)->minutesUntilLogout(),
            (int) config('session.lifetime'),
        );
    }

    public function test_boot_keeps_session_alive_for_a_full_working_day_even_if_env_says_120(): void
    {
        $this->assertSame(120, (int) env('SESSION_LIFETIME'));
        $this->assertGreaterThanOrEqual(
            StaffDailySessionService::WORKING_DAY_MINUTES,
            (int) config('session.lifetime'),
        );
    }

    public function test_each_admin_page_keeps_the_cookie_until_8pm_not_two_hours(): void
    {
        $this->enableAdminAccess();
        Carbon::setTestNow(Carbon::parse('2026-09-21 11:00:00', 'Asia/Kolkata'));

        $user = $this->staffUser();
        $service = app(StaffDailySessionService::class);

        $this->actingAs($user);
        session([
            StaffDailySessionService::SESSION_KEY => $service->nextLogoutAt()->toIso8601String(),
        ]);

        $this->get(Dashboard::getUrl())->assertOk();

        $this->assertSame($service->minutesUntilLogout(), (int) config('session.lifetime'));
        $this->assertGreaterThan(120, (int) config('session.lifetime'));
    }

    public function test_staff_stay_signed_in_after_three_hours_idle_before_8pm(): void
    {
        $this->enableAdminAccess();
        Carbon::setTestNow(Carbon::parse('2026-09-21 11:00:00', 'Asia/Kolkata'));

        $user = $this->staffUser();
        $service = app(StaffDailySessionService::class);

        $this->actingAs($user);
        session([
            StaffDailySessionService::SESSION_KEY => $service->nextLogoutAt()->toIso8601String(),
        ]);

        $this->get(Dashboard::getUrl())->assertOk();
        $this->assertAuthenticatedAs($user);

        Carbon::setTestNow(Carbon::parse('2026-09-21 14:00:00', 'Asia/Kolkata'));

        $this->get(Dashboard::getUrl())->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_staff_are_logged_out_after_8pm_and_must_sign_in_again(): void
    {
        $this->enableAdminAccess();
        Carbon::setTestNow(Carbon::parse('2026-09-21 19:50:00', 'Asia/Kolkata'));

        $user = $this->staffUser();
        $this->actingAs($user);
        session([
            StaffDailySessionService::SESSION_KEY => Carbon::parse('2026-09-21 20:00:00', 'Asia/Kolkata')->toIso8601String(),
        ]);

        $this->get(Dashboard::getUrl())->assertOk();
        $this->assertAuthenticatedAs($user);

        Carbon::setTestNow(Carbon::parse('2026-09-21 20:00:01', 'Asia/Kolkata'));

        $this->get(Dashboard::getUrl())
            ->assertRedirect(route('staff.otp-login'));

        $this->assertGuest();
    }

    public function test_login_on_a_second_device_signs_the_first_device_out(): void
    {
        $this->enableAdminAccess();
        Carbon::setTestNow(Carbon::parse('2026-09-21 11:00:00', 'Asia/Kolkata'));

        $user = $this->staffUser();
        $service = app(StaffDailySessionService::class);

        $this->actingAs($user);
        session([
            StaffDailySessionService::SESSION_KEY => $service->nextLogoutAt()->toIso8601String(),
        ]);

        $this->get(Dashboard::getUrl())->assertOk();
        $this->assertAuthenticatedAs($user);

        \Illuminate\Support\Facades\Cache::put(
            $service->deviceCacheKey($user->id),
            'other-device-token',
            now()->addHours(8),
        );

        $this->get(Dashboard::getUrl())
            ->assertRedirect(route('staff.otp-login'));

        $this->assertGuest();
    }

    public function test_command_closes_morning_logins_at_8pm_but_not_evening_logins(): void
    {
        $user = $this->staffUser();
        $service = app(StaffDailySessionService::class);

        StaffLoginSession::query()->create([
            'user_id' => $user->id,
            'logged_in_at' => Carbon::parse('2026-09-21 10:15:00', 'Asia/Kolkata'),
            'logged_out_at' => null,
            'method' => StaffLoginMethod::Otp,
        ]);
        StaffLoginSession::query()->create([
            'user_id' => $user->id,
            'logged_in_at' => Carbon::parse('2026-09-21 20:30:00', 'Asia/Kolkata'),
            'logged_out_at' => null,
            'method' => StaffLoginMethod::Otp,
        ]);

        Carbon::setTestNow(Carbon::parse('2026-09-21 19:59:00', 'Asia/Kolkata'));
        $this->assertSame(0, $service->closeExpiredLoginLogs());

        Carbon::setTestNow(Carbon::parse('2026-09-21 20:01:00', 'Asia/Kolkata'));
        $this->assertSame(1, $service->closeExpiredLoginLogs());

        $morning = StaffLoginSession::query()->orderBy('id')->first();
        $evening = StaffLoginSession::query()->orderByDesc('id')->first();

        $this->assertNotNull($morning->logged_out_at);
        $this->assertSame('20:00', $morning->logged_out_at->timezone('Asia/Kolkata')->format('H:i'));
        $this->assertNull($evening->logged_out_at);
    }

    protected function staffUser(): User
    {
        Role::findOrCreate(RoleName::SuperAdmin->value);

        $user = User::factory()->create([
            'is_active' => true,
            'is_platform_operator' => false,
        ]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function enableAdminAccess(): void
    {
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
    }
}
