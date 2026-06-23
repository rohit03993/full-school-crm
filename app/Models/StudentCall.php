<?php

namespace App\Models;

use App\Enums\CallDirection;
use App\Enums\CallStatus;
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
        'user_id',
        'call_status',
        'call_direction',
        'whatsapp_auto_status',
        'who_answered',
        'duration_minutes',
        'call_notes',
        'tags',
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
            'visit_status_changed_to' => VisitStatus::class,
            'next_followup_at' => 'datetime',
            'called_at' => 'datetime',
            'duration_minutes' => 'integer',
            'tags' => 'array',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
