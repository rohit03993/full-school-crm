<?php

namespace App\Services;

use App\Enums\StaffLoginMethod;
use App\Models\StaffLoginSession;
use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Lightweight CRM sign-in / sign-out log (not biometric punch attendance).
 * One insert on login, one update on logout. Failures never block sign-in.
 */
class StaffLoginSessionService
{
    public const RETAIN_DAYS = 180;

    public function recordLogin(User $user, ?Request $request = null): void
    {
        if ($user->isPlatformOperator()) {
            return;
        }

        $request ??= request();
        $now = now();
        $sessionKey = $this->sessionKey($request);

        StaffLoginSession::query()
            ->where('user_id', $user->id)
            ->whereNull('logged_out_at')
            ->update(['logged_out_at' => $now]);

        StaffLoginSession::query()->create([
            'user_id' => $user->id,
            'logged_in_at' => $now,
            'logged_out_at' => null,
            'method' => $this->detectMethod($request)->value,
            'ip_address' => $request?->ip(),
            'user_agent' => $this->truncateAgent($request?->userAgent()),
            'session_key' => $sessionKey,
        ]);
    }

    public function recordLogout(User $user, ?Request $request = null): void
    {
        if ($user->isPlatformOperator()) {
            return;
        }

        $request ??= request();
        $sessionKey = $this->sessionKey($request);
        $now = now();

        $updated = 0;

        if ($sessionKey !== null) {
            $updated = StaffLoginSession::query()
                ->where('user_id', $user->id)
                ->where('session_key', $sessionKey)
                ->whereNull('logged_out_at')
                ->update(['logged_out_at' => $now]);
        }

        if ($updated > 0) {
            return;
        }

        $open = StaffLoginSession::query()
            ->where('user_id', $user->id)
            ->whereNull('logged_out_at')
            ->orderByDesc('id')
            ->first();

        $open?->update(['logged_out_at' => $now]);
    }

    public function handleLoginEvent(Login $event): void
    {
        try {
            if ($event->guard === 'platform' || ! $event->user instanceof User) {
                return;
            }

            $this->recordLogin($event->user);
        } catch (\Throwable $exception) {
            Log::warning('Staff login session record failed: '.$exception->getMessage());
        }
    }

    public function handleLogoutEvent(Logout $event): void
    {
        try {
            if ($event->guard === 'platform' || ! $event->user instanceof User) {
                return;
            }

            $this->recordLogout($event->user);
        } catch (\Throwable $exception) {
            Log::warning('Staff logout session record failed: '.$exception->getMessage());
        }
    }

    public function pruneOldSessions(): int
    {
        return StaffLoginSession::query()
            ->where('logged_in_at', '<', now()->subDays(self::RETAIN_DAYS))
            ->delete();
    }

    protected function detectMethod(?Request $request): StaffLoginMethod
    {
        if ($request === null) {
            return StaffLoginMethod::Password;
        }

        if ($request->routeIs('staff.otp-login.*') || str_contains($request->path(), 'otp-login')) {
            return StaffLoginMethod::Otp;
        }

        return StaffLoginMethod::Password;
    }

    protected function sessionKey(?Request $request): ?string
    {
        $id = $request?->hasSession() ? (string) $request->session()->getId() : '';

        if ($id === '') {
            return null;
        }

        return hash('sha256', $id);
    }

    protected function truncateAgent(?string $agent): ?string
    {
        if ($agent === null || $agent === '') {
            return null;
        }

        return mb_substr($agent, 0, 191);
    }
}
