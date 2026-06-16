<?php

namespace App\Http\Controllers\StudentPortal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StudentPortal\Concerns\ResolvesPortalStudent;
use App\Services\IdCardService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class IdCardDownloadController extends Controller
{
    use ResolvesPortalStudent;

    public function download(): StreamedResponse
    {
        $student = $this->portalStudent();
        $enrollment = $student->activeEnrollment;

        abort_unless($enrollment, 404);
        abort_unless($enrollment->hasIdCard(), 404);
        abort_unless(Storage::disk(IdCardService::DISK)->exists($enrollment->id_card_path), 404);

        return Storage::disk(IdCardService::DISK)->download(
            $enrollment->id_card_path,
            "{$enrollment->enrollment_number}-id-card.pdf",
        );
    }
}
