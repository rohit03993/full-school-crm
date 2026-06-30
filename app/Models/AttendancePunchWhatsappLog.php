<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendancePunchWhatsappLog extends Model
{
    protected $fillable = [
        'student_id',
        'enrollment_number',
        'state',
        'punch_date',
        'punch_time',
        'phone',
        'status',
        'error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'punch_date' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
