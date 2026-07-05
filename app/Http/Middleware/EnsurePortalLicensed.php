<?php

namespace App\Http\Middleware;

use App\Enums\LicenseFeature;
use App\Support\FeatureGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalLicensed
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (FeatureGate::licenseActive() && FeatureGate::enabled(LicenseFeature::Portal)) {
            return $next($request);
        }

        if ($request->routeIs('portal.login', 'portal.login.submit')) {
            return redirect()
                ->route('login')
                ->with('portal_unavailable', 'Student portal is temporarily unavailable. Please contact the institute office.');
        }

        abort(404);
    }
}
