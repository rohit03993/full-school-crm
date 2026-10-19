<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDrop extends Model
{
    protected $fillable = [
        'student_id',
        'enrollment_id',
        'batch_id',
        'reason',
        'dropped_by_user_id',
        'dropped_at',
        'reactivated_by_user_id',
        'reactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'dropped_at' => 'datetime',
            'reactivated_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function droppedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dropped_by_user_id');
    }

    public function reactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reactivated_by_user_id');
    }
}
