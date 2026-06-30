<?php

namespace App\Http\Middleware;

use App\Enums\LicenseFeature;
use App\Support\FeatureGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicenseFeature
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $licenseFeature = LicenseFeature::tryFrom($feature);

        if ($licenseFeature === null || ! FeatureGate::enabled($licenseFeature)) {
            abort(404);
        }

        return $next($request);
    }
}
