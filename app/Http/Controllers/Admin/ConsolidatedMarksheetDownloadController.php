<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ConsolidatedMarksheetPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConsolidatedMarksheetDownloadController extends Controller
{
    public function download(Request $request): StreamedResponse
    {
        abort_unless(auth()->check(), 403);

        $path = (string) $request->query('path', '');

        if (! str_starts_with($path, 'marksheets/consolidated/')) {
            abort(404);
        }

        abort_unless(Storage::disk(ConsolidatedMarksheetPdfService::DISK)->exists($path), 404);

        $filename = basename($path);

        return Storage::disk(ConsolidatedMarksheetPdfService::DISK)->download($path, $filename);
    }
}
