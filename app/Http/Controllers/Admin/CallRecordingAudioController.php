<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CallRecording;
use App\Services\BatchStaffAssignmentService;
use App\Services\CallIntelligence\CallIntelligenceClient;
use App\Support\CrmAccess;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CallRecordingAudioController extends Controller
{
    public function __invoke(CallRecording $callRecording, CallIntelligenceClient $client, BatchStaffAssignmentService $assignments): Response
    {
        abort_unless(Auth::check(), 403);
        abort_unless(CrmAccess::canViewCallLog(Auth::user()), 403);
        abort_unless($callRecording->student !== null, 404);
        abort_unless($assignments->canViewStudent(Auth::user(), $callRecording->student), 403);
        abort_unless($client->enabled(), 404);

        $remote = $client->audioResponse($callRecording);

        abort_unless($remote->successful(), 404);

        return response($remote->body(), 200, [
            'Content-Type' => $callRecording->audio_mime_type ?: ($remote->header('Content-Type') ?: 'audio/mpeg'),
            'Content-Disposition' => 'inline; filename="'.$callRecording->public_id.'"',
        ]);
    }
}
