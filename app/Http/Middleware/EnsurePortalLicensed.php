<?php

namespace App\Http\Middleware;

use Closure;
use App\Support\FeatureGate;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalLicensed
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! FeatureGate::licenseActive() || ! FeatureGate::enabled(\App\Enums\LicenseFeature::Portal)) {
            abort(404);
        }

        return $next($request);
    }
}
