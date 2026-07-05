<?php

namespace App\Http\Middleware;

use App\Filament\Pages\LicenseExpiredPage;
use App\Support\FeatureGate;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicenseActive
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Filament::getCurrentPanel()?->getId() !== 'admin') {
            return $next($request);
        }

        if ($request->routeIs('filament.admin.auth.*')) {
            return $next($request);
        }

        if ($request->routeIs('filament.admin.pages.license-expired')) {
            return $next($request);
        }

        if (! FeatureGate::licenseExpired()) {
            return $next($request);
        }

        return redirect()->to(LicenseExpiredPage::getUrl());
    }
}
