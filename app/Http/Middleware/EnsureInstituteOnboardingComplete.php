<?php

namespace App\Http\Middleware;

use App\Enums\RoleName;
use App\Filament\Pages\FirstRunSetup;
use App\Support\InstituteOnboarding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstituteOnboardingComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->hasRole(RoleName::SuperAdmin->value)) {
            return $next($request);
        }

        if (InstituteOnboarding::isComplete()) {
            return $next($request);
        }

        $setupPath = trim(parse_url(FirstRunSetup::getUrl(), PHP_URL_PATH) ?? '', '/');

        if ($request->is($setupPath) || $request->is('admin/logout')) {
            return $next($request);
        }

        return redirect()->to(FirstRunSetup::getUrl());
    }
}
