<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetaWhatsAppMessage;
use App\Services\MetaWhatsAppMediaService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MetaWhatsAppMediaController extends Controller
{
    public function show(MetaWhatsAppMessage $message): StreamedResponse
    {
        abort_unless(auth()->check(), 403);
        abort_unless(filled($message->media_path), 404);
        abort_unless(Storage::disk(MetaWhatsAppMediaService::DISK)->exists((string) $message->media_path), 404);

        $filename = (string) ($message->media_filename ?: basename((string) $message->media_path));
        $mimeType = (string) ($message->media_mime_type ?: 'application/octet-stream');

        return Storage::disk(MetaWhatsAppMediaService::DISK)->response(
            (string) $message->media_path,
            $filename,
            ['Content-Type' => $mimeType],
        );
    }
}
