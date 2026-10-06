<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CallRecording;
use App\Services\CallIntelligence\CallIntelligenceSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallIntelligenceResultController extends Controller
{
    public function __invoke(Request $request, CallIntelligenceSettings $settings): JsonResponse
    {
        $settings->apply();
        $secret = (string) config('call_intelligence.callback_secret');
        $raw = $request->getContent();
        $provided = (string) $request->bearerToken();
        $signature = (string) $request->header('X-Call-Ai-Signature');
        $expected = $secret === '' ? '' : hash_hmac('sha256', $raw, $secret);

        if ($secret === '' || $provided === '' || $signature === ''
            || ! hash_equals($secret, $provided)
            || ! hash_equals($expected, $signature)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $payload = $request->json()->all();
        $callId = (string) ($payload['call_id'] ?? '');
        $recording = CallRecording::query()->where('public_id', $callId)->first();

        if ($recording === null) {
            return response()->json(['message' => 'Call not found'], 404);
        }

        $analysis = is_array($payload['ai_analysis'] ?? null) ? $payload['ai_analysis'] : null;
        $followUp = (bool) ($analysis['follow_up_required'] ?? false);

        $recording->update([
            'processing_status' => (string) ($payload['status'] ?? 'COMPLETED'),
            'duration_seconds' => $payload['duration_seconds'] ?? $recording->duration_seconds,
            'summary' => $payload['summary'] ?? null,
            'short_summary' => $payload['short_summary'] ?? null,
            'transcript_text' => $payload['transcript_text'] ?? $recording->transcript_text,
            'transcript_json' => $payload['transcript_json'] ?? $recording->transcript_json,
            'ai_analysis_json' => $analysis,
            'follow_up_required' => $followUp,
            'follow_up_reason' => $followUp ? ($analysis['follow_up_reason'] ?? null) : null,
            'suggested_follow_up_date' => $followUp ? ($analysis['suggested_follow_up_date'] ?? null) : null,
            'suggested_follow_up_time' => $followUp ? ($analysis['suggested_follow_up_time'] ?? null) : null,
            'processing_error' => $payload['error'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }
}
