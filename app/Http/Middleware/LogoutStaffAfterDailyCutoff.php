<?php

namespace App\Http\Middleware;

use App\Services\StaffDailySessionService;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogoutStaffAfterDailyCutoff
{
    public function __construct(
        protected StaffDailySessionService $sessions,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Filament::getCurrentPanel()?->getId() !== 'admin') {
            return $next($request);
        }

        if ($request->routeIs('filament.admin.auth.*') || $request->routeIs('staff.otp-login.*')) {
            return $next($request);
        }

        $user = $request->user();

        if ($user === null || $user->isPlatformOperator()) {
            return $next($request);
        }

        $this->sessions->ensureStarted($request);

        if (! $this->sessions->isCurrentDevice($user, $request)) {
            $this->sessions->logoutStaff($request);

            return redirect()
                ->route('staff.otp-login')
                ->with('otp_success', 'You were signed out because this account logged in on another device. Sign in again with a new WhatsApp OTP.');
        }

        if (! $this->sessions->hasExpired($request)) {
            return $next($request);
        }

        $this->sessions->logoutStaff($request, releaseDevice: true);

        return redirect()
            ->route('staff.otp-login');
    }
}
