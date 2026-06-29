<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StudentPortal\Concerns\ResolvesPortalStudent;
use App\Models\HomeworkAssignment;
use App\Services\HomeworkAssignmentService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HomeworkController extends Controller
{
    use ResolvesPortalStudent;

    public function index(HomeworkAssignmentService $homework): View
    {
        $student = $this->portalStudent()->load('activeEnrollment');

        return view('portal.homework.index', [
            'student' => $student,
            'assignments' => $homework->assignmentsForStudent($student),
        ]);
    }

    public function show(HomeworkAssignment $homeworkAssignment, HomeworkAssignmentService $homeworkService): View
    {
        $student = $this->portalStudent()->load('activeEnrollment');

        abort_unless($homeworkService->studentCanAccess($homeworkAssignment, $student), 404);

        $homeworkService->recordView($homeworkAssignment, $student);

        return view('portal.homework.show', [
            'student' => $student,
            'homework' => $homeworkAssignment->load('batch'),
        ]);
    }

    public function view(HomeworkAssignment $homeworkAssignment, HomeworkAssignmentService $homeworkService): StreamedResponse
    {
        $student = $this->portalStudent();

        abort_unless($homeworkService->studentCanAccess($homeworkAssignment, $student), 404);
        abort_unless($homeworkAssignment->isPreviewable(), 404);

        $homeworkService->recordView($homeworkAssignment, $student);

        return $homeworkAssignment->inlineFileResponse();
    }

    public function download(HomeworkAssignment $homeworkAssignment, HomeworkAssignmentService $homeworkService): StreamedResponse
    {
        $student = $this->portalStudent();

        abort_unless($homeworkService->studentCanAccess($homeworkAssignment, $student), 404);
        abort_unless($homeworkAssignment->hasFile(), 404);

        $homeworkService->recordView($homeworkAssignment, $student);

        return $homeworkAssignment->downloadFileResponse();
    }
}
