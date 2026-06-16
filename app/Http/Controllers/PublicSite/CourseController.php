<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\View\View;

class CourseController extends Controller
{
    public function __invoke(): View
    {
        $courses = Course::query()
            ->active()
            ->orderBy('course_type')
            ->orderBy('name')
            ->orderBy('duration')
            ->get()
            ->groupBy(fn ($course) => $course->course_type->label());

        return view('public.courses', [
            'courseGroups' => $courses,
        ]);
    }
}
