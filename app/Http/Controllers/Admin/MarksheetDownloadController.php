<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StudentMarksheet;
use App\Services\MarksheetPdfService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MarksheetDownloadController extends Controller
{
    public function preview(StudentMarksheet $marksheet): StreamedResponse
    {
        abort_unless(auth()->check(), 403);
        abort_unless($marksheet->hasPdf(), 404);
        abort_unless(Storage::disk(MarksheetPdfService::DISK)->exists($marksheet->pdf_path), 404);

        $response = Storage::disk(MarksheetPdfService::DISK)->response(
            $marksheet->pdf_path,
            $this->filename($marksheet),
            ['Content-Type' => 'application/pdf'],
        );

        $response->headers->set('Cache-Control', 'private, max-age=3600');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        return $response;
    }

    public function download(StudentMarksheet $marksheet): StreamedResponse
    {
        abort_unless(auth()->check(), 403);
        abort_unless($marksheet->hasPdf(), 404);
        abort_unless(Storage::disk(MarksheetPdfService::DISK)->exists($marksheet->pdf_path), 404);

        return Storage::disk(MarksheetPdfService::DISK)->download(
            $marksheet->pdf_path,
            $this->filename($marksheet),
        );
    }

    protected function filename(StudentMarksheet $marksheet): string
    {
        $marksheet->loadMissing('student');

        $name = str($marksheet->student?->name ?? 'student')->slug('-');

        return "marksheet-{$name}-{$marksheet->formattedSerial()}.pdf";
    }
}
