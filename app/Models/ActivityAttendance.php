<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityAttendance extends Model
{
    protected $fillable = [
        'attendable_type',
        'attendable_id',
        'student_id',
        'is_present',
        'marks_obtained',
        'grade',
        'remarks',
        'marked_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_present' => 'boolean',
            'marks_obtained' => 'decimal:2',
        ];
    }

    public function attendable(): MorphTo
    {
        return $this->morphTo();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by_user_id');
    }
}
