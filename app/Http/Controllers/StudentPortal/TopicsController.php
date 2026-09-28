<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StudentPortal\Concerns\ResolvesPortalStudent;
use App\Services\SectionCoursePlanService;
use Illuminate\View\View;

class TopicsController extends Controller
{
    use ResolvesPortalStudent;

    public function index(SectionCoursePlanService $plans): View
    {
        $student = $this->portalStudent()->load('activeEnrollment');

        return view('portal.topics.index', [
            'student' => $student,
            'groups' => $plans->finalTopicsForStudent($student),
        ]);
    }
}
