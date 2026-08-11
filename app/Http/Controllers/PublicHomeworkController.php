<?php

namespace App\Http\Controllers;

use App\Models\HomeworkAssignment;
use App\Support\InstituteSettings;
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
        abort_unless(strlen($token) >= 24, 404);

        return HomeworkAssignment::query()
            ->where('public_token', $token)
            ->whereNotNull('published_at')
            ->firstOrFail();
    }
}
