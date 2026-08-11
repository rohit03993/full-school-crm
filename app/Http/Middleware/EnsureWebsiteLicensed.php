<?php

namespace App\Http\Middleware;

use App\Enums\LicenseFeature;
use App\Support\FeatureGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWebsiteLicensed
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (FeatureGate::enabled(LicenseFeature::Website)) {
            return $next($request);
        }

        return response()->view('public.website-unavailable', [
            'instituteName' => config('app.name'),
        ], 503);
    }
}
