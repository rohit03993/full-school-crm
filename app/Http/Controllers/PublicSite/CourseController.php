<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Support\InstituteProfile;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function __invoke(): View
    {
        $courses = InstituteProfile::publicCoursesQuery(Course::query())
            ->orderBy('name')
            ->orderBy('duration')
            ->get();

        return view('public.courses', [
            'courses' => $courses,
        ]);
    }
}
