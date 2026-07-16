<?php

namespace App\Http\Controllers\Display;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Services\DocumentService;
use App\Services\Punch\AttendanceDisplayService;
use App\Services\Punch\AttendanceDisplaySettingsService;
use App\Support\InstituteSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttendanceDisplayController extends Controller
{
    public function __construct(
        protected AttendanceDisplaySettingsService $settings,
        protected AttendanceDisplayService $display,
    ) {}

    public function show(Request $request): View
    {
        $latest = $this->display->latestPunchToday();
        $institute = InstituteSettings::forDocuments();

        return view('display.attendance', [
            'instituteName' => $institute['name'] ?? config('app.name'),
            'instituteLogo' => InstituteSettings::panelLogoUrl(),
            'token' => (string) $request->route('token'),
            'latestPunch' => $latest,
            'maxPunchId' => $this->display->maxPunchLogId(),
            'pollIntervalMs' => max(1000, (int) config('attendance_display.poll_interval_ms', 2500)),
            'cardDurationMs' => max(3000, (int) config('attendance_display.card_duration_ms', 10000)),
            'latestUrl' => route('display.attendance.latest', ['token' => $request->route('token')]),
        ]);
    }

    public function latest(Request $request): JsonResponse
    {
        $sinceId = max(0, (int) $request->query('since', 0));
        $punches = $this->display->punchesSince($sinceId);

        return response()->json([
            'punches' => $punches,
            'max_id' => $this->display->maxPunchLogId(),
        ]);
    }

    public function photo(Request $request, Document $document): StreamedResponse
    {
        if (! $request->hasValidSignature()) {
            abort(403);
        }

        $fingerprint = (string) $request->query('display', '');

        if ($fingerprint === '' || ! hash_equals((string) $this->settings->tokenFingerprint(), $fingerprint)) {
            abort(403);
        }

        if (! $this->settings->isEnabled()) {
            abort(404);
        }

        abort_unless($document->type === DocumentType::Photo, 404);
        abort_unless($document->isImage(), 404);
        abort_unless(
            \Illuminate\Support\Facades\Storage::disk(DocumentService::DISK)->exists($document->file_path),
            404,
        );

        return \Illuminate\Support\Facades\Storage::disk(DocumentService::DISK)->response(
            $document->file_path,
            $document->original_filename,
        );
    }
}
