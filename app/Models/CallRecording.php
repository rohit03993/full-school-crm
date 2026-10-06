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
            'UPLOADED', 'QUEUED', 'PROCESSING_AUDIO', 'RETRY_PENDING' => 'Processing...',
            'TRANSCRIBING' => 'Transcribing...',
            'TRANSCRIPT_READY', 'ANALYZING' => 'Generating AI summary...',
            'AI_READY', 'CRM_SYNCED', 'COMPLETED' => 'Completed',
            'FAILED' => filled($this->transcript_text)
                ? 'AI processing failed.'
                : 'Processing failed.',
            default => 'Processing...',
        };
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
