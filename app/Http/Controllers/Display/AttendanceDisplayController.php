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
        $batchId = $this->parseBatchId($request);
        $stateFilter = $this->parseStateFilter($request);
        $latest = $this->display->latestPunchToday();
        $institute = InstituteSettings::forDocuments();

        if ($latest !== null && ! $this->display->matchesFiltersPublic($latest, $batchId, $stateFilter)) {
            $latest = null;
        }

        return view('display.attendance', [
            'instituteName' => $institute['name'] ?? config('app.name'),
            'instituteLogo' => InstituteSettings::panelLogoUrl(),
            'token' => (string) $request->route('token'),
            'latestPunch' => $latest,
            'maxPunchId' => $this->display->maxPunchLogId(),
            'pollIntervalMs' => max(1000, (int) config('attendance_display.poll_interval_ms', 2500)),
            'cardDurationMs' => max(3000, (int) config('attendance_display.card_duration_ms', 10000)),
            'latestUrl' => route('display.attendance.latest', ['token' => $request->route('token')]),
            'batchOptions' => $this->display->batchOptions(),
            'initialSummary' => $this->display->summaryForToday($batchId),
            'initialRecent' => $this->display->recentPunchesToday(10, $batchId, $stateFilter),
            'initialBatchId' => $batchId,
            'initialState' => $stateFilter,
        ]);
    }

    public function latest(Request $request): JsonResponse
    {
        $sinceId = max(0, (int) $request->query('since', 0));
        $batchId = $this->parseBatchId($request);
        $stateFilter = $this->parseStateFilter($request);

        return response()->json([
            'punches' => $this->display->punchesSince($sinceId, 20, $batchId, $stateFilter),
            'recent' => $this->display->recentPunchesToday(10, $batchId, $stateFilter),
            'summary' => $this->display->summaryForToday($batchId),
            'batch_options' => $this->display->batchOptions(),
            'max_id' => $this->display->maxPunchLogId(),
            'filters' => [
                'batch_id' => $batchId,
                'state' => $stateFilter,
            ],
        ]);
    }

    private function parseBatchId(Request $request): ?int
    {
        $batchId = $request->query('batch_id');

        if (! filled($batchId)) {
            return null;
        }

        $batchId = (int) $batchId;

        return $batchId > 0 ? $batchId : null;
    }

    private function parseStateFilter(Request $request): ?string
    {
        $state = strtoupper(trim((string) $request->query('state', '')));

        return in_array($state, ['IN', 'OUT'], true) ? $state : null;
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
