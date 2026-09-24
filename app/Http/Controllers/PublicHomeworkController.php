<?php

namespace App\Http\Controllers;

use App\Models\HomeworkAssignment;
use App\Models\HomeworkStudentLink;
use App\Services\HomeworkAssignmentService;
use App\Support\InstituteSettings;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicHomeworkController extends Controller
{
    public function show(string $token): View
    {
        $homework = $this->resolvePublished($token)->load('batch');

        return view('public.homework-show', [
            'homework' => $homework,
            'instituteName' => InstituteSettings::brandName(),
        ]);
    }

    public function view(string $token): StreamedResponse
    {
        $homework = $this->resolvePublished($token);

        abort_unless($homework->isPreviewable(), 404);

        return $homework->inlineFileResponse();
    }

    public function download(string $token): StreamedResponse
    {
        $homework = $this->resolvePublished($token);

        abort_unless($homework->hasFile(), 404);

        return $homework->downloadFileResponse();
    }

    protected function resolvePublished(string $token): HomeworkAssignment
    {
        abort_unless(strlen($token) >= HomeworkAssignment::PUBLIC_TOKEN_LENGTH && strlen($token) <= 64, 404);

        $studentLink = $this->studentLinkForToken($token);

        if ($studentLink) {
            $studentLink->recordOpen();
            app(HomeworkAssignmentService::class)->recordView($studentLink->assignment, $studentLink->student);

            return $studentLink->assignment;
        }

        return HomeworkAssignment::query()
            ->where('public_token', $token)
            ->firstOrFail();
    }

    protected function studentLinkForToken(string $token): ?HomeworkStudentLink
    {
        if (! Schema::hasTable('homework_student_links')) {
            return null;
        }

        return HomeworkStudentLink::query()
            ->where('token', $token)
            ->with(['assignment', 'student'])
            ->first();
    }
}
