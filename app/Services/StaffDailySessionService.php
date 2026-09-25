<?php

namespace App\Services;

use App\Models\StaffLoginSession;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Staff stay signed in until 8:00 PM IST the same working day, then must OTP again.
 * Login after 8:00 PM lasts until 8:00 PM the next day.
 *
 * Idle time (phone locked, PWA in background) must not ask OTP before that cutoff.
 * Laravel session files/cookies use WORKING_DAY_MINUTES so a 2-hour pause does not
 * delete the login. 8 PM logout is still hasExpired() + LogoutStaffAfterDailyCutoff.
 */
class StaffDailySessionService
{
    public const SESSION_KEY = 'staff_daily_logout_at';

    /** Minutes the login cookie/file may sit unused. Covers login after 8 PM until the next 8 PM. */
    public const WORKING_DAY_MINUTES = 1440;

    public function deviceCacheKey(int $userId): string
    {
        return 'staff_active_device:'.$userId;
    }

    public function deviceToken(Request $request): string
    {
        return hash('sha256', $request->session()->getId());
    }

    public function claimDevice(User $user, Request $request): void
    {
        if ($user->isPlatformOperator()) {
            return;
        }

        Cache::put(
            $this->deviceCacheKey($user->id),
            $this->deviceToken($request),
            now()->addMinutes($this->minutesUntilLogout()),
        );
    }

    public function isCurrentDevice(User $user, Request $request): bool
    {
        if ($user->isPlatformOperator()) {
            return true;
        }

        $allowed = Cache::get($this->deviceCacheKey($user->id));

        if (! is_string($allowed) || $allowed === '') {
            $this->claimDevice($user, $request);

            return true;
        }

        return hash_equals($allowed, $this->deviceToken($request));
    }

    public function timezone(): string
    {
        return (string) config('staff.daily_logout_timezone', 'Asia/Kolkata');
    }

    public function logoutTime(): string
    {
        $time = trim((string) config('staff.daily_logout_time', '20:00'));

        return $time !== '' ? $time : '20:00';
    }

    public function now(?CarbonInterface $now = null): Carbon
    {
        return Carbon::parse($now ?? now())->timezone($this->timezone());
    }

    public function nextLogoutAt(?CarbonInterface $now = null): Carbon
    {
        $now = $this->now($now);
        $end = $now->copy()->setTimeFromTimeString($this->logoutTime());

        if ($now->gte($end)) {
            $end->addDay();
        }

        return $end;
    }

    public function todayCutoff(?CarbonInterface $now = null): Carbon
    {
        return $this->now($now)->copy()->setTimeFromTimeString($this->logoutTime());
    }

    public function minutesUntilLogout(?CarbonInterface $now = null): int
    {
        $now = $this->now($now);
        $seconds = $now->diffInSeconds($this->nextLogoutAt($now), false);

        return max(1, (int) ceil($seconds / 60));
    }

    /**
     * Keep the session file alive for a full working day, even if .env still says 120.
     * Call this at app boot — before Laravel reads the session — or idle 2 hours deletes the login.
     */
    public function applyWorkingDayLifetime(): void
    {
        $current = (int) config('session.lifetime');

        config(['session.lifetime' => max($current, self::WORKING_DAY_MINUTES)]);
    }

    /**
     * Browser cookie should end around 8 PM. Call only after the session is already open.
     * Do not call this at boot: shrinking lifetime before read would kill a long idle pause.
     */
    public function applyCookieLifetime(?CarbonInterface $now = null): void
    {
        config(['session.lifetime' => $this->minutesUntilLogout($now)]);
    }

    public function start(Request $request, ?CarbonInterface $now = null): void
    {
        $ends = $this->nextLogoutAt($now);
        $request->session()->put(self::SESSION_KEY, $ends->toIso8601String());
        $this->applyCookieLifetime($now);
    }

    public function ensureStarted(Request $request, ?CarbonInterface $now = null): void
    {
        if (! $request->session()->has(self::SESSION_KEY)) {
            $now = $this->now($now);
            $todayCutoff = $this->todayCutoff($now);

            if ($now->gte($todayCutoff)) {
                $request->session()->put(self::SESSION_KEY, $now->copy()->subSecond()->toIso8601String());
            } else {
                $request->session()->put(self::SESSION_KEY, $todayCutoff->toIso8601String());
            }
        }

        $this->applyCookieLifetime($now);
    }

    public function hasExpired(Request $request, ?CarbonInterface $now = null): bool
    {
        $raw = $request->session()->get(self::SESSION_KEY);

        if (! is_string($raw) || $raw === '') {
            return false;
        }

        try {
            $ends = Carbon::parse($raw)->timezone($this->timezone());
        } catch (\Throwable) {
            return true;
        }

        return $this->now($now)->gte($ends);
    }

    public function startFromLoginEvent(Login $event): void
    {
        if ($event->guard === 'platform' || ! $event->user instanceof User) {
            return;
        }

        if ($event->user->isPlatformOperator()) {
            return;
        }

        try {
            $request = request();

            if (! $request->hasSession()) {
                $request->setLaravelSession(app('session.store'));
            }
        } catch (\Throwable) {
            return;
        }

        if (! $request->hasSession()) {
            return;
        }

        $this->start($request);
    }

    public function logoutStaff(Request $request, bool $releaseDevice = false): void
    {
        $user = $request->user();

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($releaseDevice && $user instanceof User) {
            Cache::forget($this->deviceCacheKey($user->id));
        }
    }

    public function closeExpiredLoginLogs(?CarbonInterface $now = null): int
    {
        $now = $this->now($now);
        $cutoff = $this->todayCutoff($now);

        if ($now->lt($cutoff)) {
            return 0;
        }

        return StaffLoginSession::query()
            ->whereNull('logged_out_at')
            ->where('logged_in_at', '<', $cutoff)
            ->update(['logged_out_at' => $cutoff]);
    }
}
