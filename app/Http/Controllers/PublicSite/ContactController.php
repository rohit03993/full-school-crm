<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePublicEnquiryRequest;
use App\Models\Course;
use App\Services\EnquiryService;
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
        ]);
    }

    public function store(StorePublicEnquiryRequest $request, EnquiryService $enquiryService): RedirectResponse
    {
        $enquiry = $enquiryService->create($request->validated());

        return redirect()
            ->route('contact')
            ->with('enquiry_success', [
                'number' => $enquiry->enquiry_number,
                'name' => $enquiry->student->name,
            ]);
    }
}
