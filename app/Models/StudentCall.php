<?php

namespace App\Models;

use App\Enums\CallDirection;
use App\Enums\CallStatus;
use App\Enums\EnrolledCallPurpose;
use App\Enums\VisitStatus;
use App\Enums\WhatsAppAutoStatus;
use App\Enums\WhoAnswered;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCall extends Model
{
    protected $fillable = [
        'student_id',
        'enquiry_id',
        'student_case_id',
        'user_id',
        'call_status',
        'call_direction',
        'whatsapp_auto_status',
        'who_answered',
        'duration_minutes',
        'duration_seconds',
        'call_notes',
        'tags',
        'call_purpose',
        'visit_status_changed_to',
        'next_followup_at',
        'called_at',
    ];

    protected function casts(): array
    {
        return [
            'call_status' => CallStatus::class,
            'call_direction' => CallDirection::class,
            'whatsapp_auto_status' => WhatsAppAutoStatus::class,
            'who_answered' => WhoAnswered::class,
            'call_purpose' => EnrolledCallPurpose::class,
            'visit_status_changed_to' => VisitStatus::class,
            'next_followup_at' => 'datetime',
            'called_at' => 'datetime',
            'duration_minutes' => 'integer',
            'duration_seconds' => 'integer',
            'tags' => 'array',
        ];
    }

    public function durationLabel(): string
    {
        $minutes = max(0, (int) ($this->duration_minutes ?? 0));
        $seconds = max(0, min(59, (int) ($this->duration_seconds ?? 0)));

        if ($minutes === 0 && $seconds === 0) {
            return '0s';
        }

        if ($minutes === 0) {
            return $seconds.'s';
        }

        if ($seconds === 0) {
            return $minutes.'m';
        }

        return $minutes.'m '.$seconds.'s';
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function studentCase(): BelongsTo
    {
        return $this->belongsTo(StudentCase::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
