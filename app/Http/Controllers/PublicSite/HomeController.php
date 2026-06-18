<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Support\InstituteProfile;
use App\Models\Course;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $courses = InstituteProfile::scopeCourses(Course::query())
            ->active()
            ->orderBy('programme_category')
            ->orderBy('duration_type')
            ->orderBy('duration')
            ->get()
            ->groupBy(fn ($course) => $course->programme_category->label());

        return view('public.home', [
            'courseGroups' => $courses,
        ]);
    }
}
