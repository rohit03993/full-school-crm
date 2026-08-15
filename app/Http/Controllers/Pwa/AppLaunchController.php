<?php

namespace App\Http\Controllers\Pwa;

use App\Enums\LicenseFeature;
use App\Http\Controllers\Controller;
use App\Support\FeatureGate;
use App\Support\SiteContent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Single PWA entry: one home-screen icon, then route by who is signed in.
 *
 * Staff (Filament/web auth) → Admin CRM.
 * Parent/student (portal session) → Student Portal.
 * Guest → branded chooser so they pick Staff or Parent/Student login.
 */
class AppLaunchController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|View
    {
        if ($request->user()) {
            return redirect('/admin');
        }

        if ($request->session()->has('student_portal_id')) {
            return redirect()->route('portal.dashboard');
        }

        return view('pwa.app-launch', [
            'institute' => SiteContent::institute(),
            'portalAvailable' => FeatureGate::licenseActive()
                && FeatureGate::enabled(LicenseFeature::Portal),
        ]);
    }
}
