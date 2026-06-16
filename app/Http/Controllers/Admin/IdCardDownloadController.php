<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Services\IdCardService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IdCardDownloadController extends Controller
{
    public function preview(Enrollment $enrollment): StreamedResponse
    {
        abort_unless(auth()->check(), 403);
        abort_unless($enrollment->hasIdCard(), 404);
        abort_unless(Storage::disk(IdCardService::DISK)->exists($enrollment->id_card_path), 404);

        $response = Storage::disk(IdCardService::DISK)->response(
            $enrollment->id_card_path,
            "{$enrollment->enrollment_number}-id-card.pdf",
            ['Content-Type' => 'application/pdf'],
        );

        $response->headers->set('Cache-Control', 'private, max-age=3600');

        return $response;
    }

    public function download(Enrollment $enrollment): StreamedResponse
    {
        abort_unless(auth()->check(), 403);
        abort_unless($enrollment->hasIdCard(), 404);
        abort_unless(Storage::disk(IdCardService::DISK)->exists($enrollment->id_card_path), 404);

        return Storage::disk(IdCardService::DISK)->download(
            $enrollment->id_card_path,
            "{$enrollment->enrollment_number}-id-card.pdf",
        );
    }
}
