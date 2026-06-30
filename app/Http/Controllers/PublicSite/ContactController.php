<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePublicEnquiryRequest;
use App\Enums\LicenseFeature;
use App\Models\Course;
use App\Services\EnquiryService;
use App\Support\FeatureGate;
use App\Support\InstituteProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function __invoke(): View
    {
        $courses = InstituteProfile::publicCoursesQuery(Course::query())
            ->orderBy('name')
            ->orderBy('duration')
            ->get();

        return view('public.contact', [
            'courses' => $courses,
            'enquiriesEnabled' => FeatureGate::enabled(LicenseFeature::Enquiries),
        ]);
    }

    public function store(StorePublicEnquiryRequest $request, EnquiryService $enquiryService): RedirectResponse
    {
        if (! FeatureGate::enabled(LicenseFeature::Enquiries)) {
            abort(404);
        }

        $enquiry = $enquiryService->create($request->validated());

        return redirect()
            ->route('contact')
            ->with('enquiry_success', [
                'number' => $enquiry->enquiry_number,
                'name' => $enquiry->student->name,
            ]);
    }
}
