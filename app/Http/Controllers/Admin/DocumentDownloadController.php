<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentDownloadController extends Controller
{
    public function download(Document $document): StreamedResponse
    {
        abort_unless(auth()->check(), 403);
        abort_unless(Storage::disk(DocumentService::DISK)->exists($document->file_path), 404);

        return Storage::disk(DocumentService::DISK)->download(
            $document->file_path,
            $document->original_filename,
        );
    }

    public function preview(Document $document): StreamedResponse
    {
        abort_unless(auth()->check(), 403);

        abort_unless($document->isPreviewableInBrowser(), 404);
        abort_unless(Storage::disk(DocumentService::DISK)->exists($document->file_path), 404);

        return Storage::disk(DocumentService::DISK)->response(
            $document->file_path,
            $document->original_filename,
        );
    }
}
