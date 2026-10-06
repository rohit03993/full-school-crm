<?php

namespace App\Services\CallIntelligence;

use App\Models\CallRecording;
use App\Models\Student;
use App\Support\IndianMobileNumber;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CallIntelligenceClient
{
    public function __construct(private CallIntelligenceSettings $settings) {}

    public function enabled(): bool
    {
        return $this->settings->enabled();
    }

    public function upload(Student $student, UploadedFile $file, string $direction): CallRecording
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Call AI is not switched on.');
        }

        $publicId = (string) Str::uuid();
        $recording = CallRecording::query()->create([
            'public_id' => $publicId,
            'student_id' => $student->id,
            'user_id' => Auth::id(),
            'phone_number' => IndianMobileNumber::normalize($student->mobile),
            'call_direction' => $direction === 'incoming' ? 'incoming' : 'outgoing',
            'audio_mime_type' => $file->getMimeType() ?: $file->getClientMimeType(),
            'processing_status' => 'UPLOAD_PENDING',
            'follow_up_required' => false,
        ]);

        try {
            $this->send($recording, $file);
        } catch (RuntimeException $exception) {
            CallRecording::query()->whereKey($recording->id)->update([
                'processing_status' => 'FAILED',
                'processing_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        return $recording->fresh();
    }

    public function refresh(CallRecording $recording): void
    {
        if (! $this->enabled() || ! $recording->isPending()) {
            return;
        }

        try {
            $response = $this->request()->get($this->url('/api/calls/'.$recording->public_id));
        } catch (ConnectionException) {
            return;
        }

        if (! $response->successful()) {
            return;
        }

        $this->applyPayload($recording, $response->json() ?? []);
    }

    public function retry(CallRecording $recording): void
    {
        if (! $this->enabled()) {
            throw new RuntimeException('Call AI is not switched on.');
        }

        $response = $this->request()->post($this->url('/api/calls/'.$recording->public_id.'/retry'));

        if (! $response->successful()) {
            throw new RuntimeException('The recording could not be retried.');
        }

        $this->applyPayload($recording, $response->json() ?? []);
    }

    /**
     * @return \Illuminate\Http\Client\Response
     */
    public function audioResponse(CallRecording $recording)
    {
        return $this->request()
            ->timeout(180)
            ->get($this->url('/api/calls/'.$recording->public_id.'/audio'));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyPayload(CallRecording $recording, array $payload): void
    {
        $analysis = is_array($payload['ai_analysis'] ?? null) ? $payload['ai_analysis'] : null;
        $followUp = (bool) ($analysis['follow_up_required'] ?? false);

        $recording->update([
            'processing_status' => (string) ($payload['status'] ?? $recording->processing_status),
            'duration_seconds' => $payload['duration_seconds'] ?? $recording->duration_seconds,
            'summary' => $payload['summary'] ?? $recording->summary,
            'short_summary' => $payload['short_summary'] ?? $recording->short_summary,
            'transcript_text' => $payload['transcript_text'] ?? $recording->transcript_text,
            'transcript_json' => $payload['transcript_json'] ?? $recording->transcript_json,
            'ai_analysis_json' => $analysis ?? $recording->ai_analysis_json,
            'follow_up_required' => $analysis === null ? (bool) $recording->follow_up_required : $followUp,
            'follow_up_reason' => $followUp ? ($analysis['follow_up_reason'] ?? null) : null,
            'suggested_follow_up_date' => $followUp ? ($analysis['suggested_follow_up_date'] ?? null) : null,
            'suggested_follow_up_time' => $followUp ? ($analysis['suggested_follow_up_time'] ?? null) : null,
            'processing_error' => $payload['error'] ?? null,
        ]);
    }

    private function send(CallRecording $recording, UploadedFile $file): void
    {
        try {
            $created = $this->request()->post($this->url('/api/calls'), [
                'call_id' => $recording->public_id,
                'phone_number' => $recording->phone_number,
                'call_direction' => strtoupper((string) $recording->call_direction),
                'recorded_at' => now()->toIso8601String(),
            ]);
        } catch (ConnectionException) {
            throw new RuntimeException('This CRM could not reach the Call AI server. Check the processing address in Setup → Call AI.');
        }

        if (! $created->successful()) {
            throw new RuntimeException($this->failureMessage($created, 'The recording could not be registered.'));
        }

        $handle = fopen($file->getRealPath(), 'r');

        if ($handle === false) {
            throw new RuntimeException('The recording could not be read.');
        }

        try {
            $uploaded = $this->request()
                ->attach('audio', $handle, $file->getClientOriginalName())
                ->post($this->url('/api/calls/'.$recording->public_id.'/audio'));
        } catch (ConnectionException) {
            throw new RuntimeException('The file reached the school site, but the Call AI server did not accept it. Check the processing address in Setup → Call AI.');
        } finally {
            fclose($handle);
        }

        if (! $uploaded->successful()) {
            throw new RuntimeException($this->failureMessage($uploaded, 'The recording could not be sent for processing.'));
        }

        $recording->update(['handed_off' => true]);
        $this->applyPayload($recording, $uploaded->json() ?? []);
    }

    private function failureMessage(Response $response, string $fallback): string
    {
        $status = $response->status();
        $detail = trim((string) $response->json('message'));

        if ($status === 401) {
            return 'The Call AI server refused this school. Check the school code and both secrets in Setup → Call AI.';
        }

        if ($status === 404) {
            return 'The processing address was reached, but the upload link was not found. Check Setup → Call AI.';
        }

        if ($detail !== '') {
            return $fallback.' '.$detail;
        }

        return $fallback.' The server replied '.$status.'.';
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $this->settings->apply();

        return Http::withToken((string) config('call_intelligence.school_secret'))
            ->withHeaders([
                'X-School-Code' => (string) config('call_intelligence.school_code'),
            ])
            ->timeout((int) config('call_intelligence.timeout_seconds'))
            ->acceptJson();
    }

    private function url(string $path): string
    {
        return rtrim((string) config('call_intelligence.api_url'), '/').$path;
    }
}
