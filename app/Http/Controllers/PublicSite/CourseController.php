<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Support\InstituteProfile;
use App\Models\Course;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function __invoke(): View
    {
        $courses = InstituteProfile::scopeCourses(Course::query())
            ->active()
            ->orderBy('programme_category')
            ->orderBy('name')
            ->orderBy('duration')
            ->get()
            ->groupBy(fn ($course) => $course->programme_category->label());

        return view('public.courses', [
            'courseGroups' => $courses,
        ]);
    }
}
