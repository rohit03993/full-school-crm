<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallRecording extends Model
{
    protected $fillable = [
        'public_id',
        'student_id',
        'user_id',
        'phone_number',
        'call_direction',
        'audio_mime_type',
        'processing_status',
        'handed_off',
        'duration_seconds',
        'transcript_text',
        'transcript_json',
        'summary',
        'short_summary',
        'ai_analysis_json',
        'follow_up_required',
        'follow_up_reason',
        'suggested_follow_up_date',
        'suggested_follow_up_time',
        'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'transcript_json' => 'array',
            'ai_analysis_json' => 'array',
            'follow_up_required' => 'boolean',
            'handed_off' => 'boolean',
            'suggested_follow_up_date' => 'date',
            'duration_seconds' => 'integer',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function friendlyStatus(): string
    {
        return match ($this->processing_status) {
            'CREATED', 'UPLOAD_PENDING' => 'Uploading...',
            'QUEUED', 'UPLOADED' => 'Waiting to start',
            'RETRY_PENDING' => 'Waiting to try again',
            'PROCESSING_AUDIO' => 'Preparing audio...',
            'TRANSCRIBING' => 'Transcribing...',
            'TRANSCRIPT_READY', 'ANALYZING' => 'Generating AI summary...',
            'AI_READY', 'CRM_SYNCED', 'COMPLETED' => 'Completed',
            'FAILED' => filled($this->transcript_text)
                ? 'AI processing failed.'
                : 'Processing failed.',
            default => 'Processing...',
        };
    }

    public function progressNote(): string
    {
        if (! $this->isPending()) {
            return '';
        }

        $minutes = $this->created_at === null ? 0 : (int) abs($this->created_at->diffInMinutes(now()));
        $waited = $minutes < 1 ? 'just now' : $minutes.' min ago';
        $estimate = $this->estimateMinutes();

        return match ($this->processing_status) {
            'PROCESSING_AUDIO' => 'The audio is being prepared. Started '.$waited.'. About '.$estimate.' min in total.',
            'TRANSCRIBING' => 'Speech is being written down. Started '.$waited.'. About '.$estimate.' min in total.',
            'TRANSCRIPT_READY', 'ANALYZING' => 'The summary is being written. Started '.$waited.'. About '.$estimate.' min in total.',
            'RETRY_PENDING' => 'This recording is waiting to try again. Started '.$waited.'.',
            default => $minutes >= 2
                ? 'Uploaded '.$waited.'. Work has not started. After it starts, a short call usually finishes in about '.$estimate.' min.'
                : 'Uploaded '.$waited.'. Waiting for work to start. About '.$estimate.' min after it starts.',
        };
    }

    private function estimateMinutes(): int
    {
        $seconds = (int) ($this->duration_seconds ?? 0);

        if ($seconds <= 0) {
            return 2;
        }

        return max(2, (int) ceil($seconds / 60) + 1);
    }

    public function canRetry(): bool
    {
        return $this->handed_off && $this->processing_status === 'FAILED';
    }

    public function isPending(): bool
    {
        return ! in_array($this->processing_status, ['COMPLETED', 'FAILED', 'AI_READY', 'CRM_SYNCED'], true);
    }

    public function clock(float $seconds): string
    {
        $seconds = max(0, (int) floor($seconds));

        return sprintf('%02d:%02d', intdiv($seconds, 60), $seconds % 60);
    }
}
