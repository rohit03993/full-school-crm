<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceManualPunch extends Model
{
    protected $fillable = [
        'enrollment_number',
        'punch_date',
        'punch_time',
        'state',
        'marked_by_user_id',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'punch_date' => 'date',
        ];
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by_user_id');
    }
}
