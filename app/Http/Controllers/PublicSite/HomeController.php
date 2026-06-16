<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $courses = Course::query()
            ->active()
            ->orderBy('course_type')
            ->orderBy('duration_type')
            ->orderBy('duration')
            ->get()
            ->groupBy(fn ($course) => $course->course_type->label());

        return view('public.home', [
            'courseGroups' => $courses,
        ]);
    }
}
