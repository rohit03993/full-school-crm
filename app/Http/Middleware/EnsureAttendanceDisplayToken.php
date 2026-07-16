<?php

namespace App\Http\Middleware;

use App\Services\Punch\AttendanceDisplaySettingsService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAttendanceDisplayToken
{
    public function __construct(
        protected AttendanceDisplaySettingsService $settings,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->settings->isEnabled()) {
            abort(404);
        }

        $token = (string) ($request->route('token') ?? $request->query('token', ''));

        if (! $this->settings->isValidToken($token)) {
            abort(403, 'Invalid display token.');
        }

        return $next($request);
    }
}
