<?php

namespace App\Http\Controllers\Admin;

use App\Enums\CrmPermission;
use App\Http\Controllers\Controller;
use App\Models\HomeworkAssignment;
use App\Support\CrmAccess;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HomeworkFileController extends Controller
{
    public function preview(HomeworkAssignment $homeworkAssignment): StreamedResponse
    {
        abort_unless(auth()->check(), 403);
        abort_unless(
            CrmAccess::canAny(Auth::user(), CrmPermission::HomeworkManage, CrmPermission::StudentsView),
            403,
        );

        return $homeworkAssignment->inlineFileResponse();
    }

    public function download(HomeworkAssignment $homeworkAssignment): StreamedResponse
    {
        abort_unless(auth()->check(), 403);
        abort_unless(
            CrmAccess::canAny(Auth::user(), CrmPermission::HomeworkManage, CrmPermission::StudentsView),
            403,
        );

        return $homeworkAssignment->downloadFileResponse();
    }
}
