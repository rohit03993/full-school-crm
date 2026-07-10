<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentCaseAssignment extends Model
{
    protected $fillable = [
        'student_case_id',
        'from_user_id',
        'to_user_id',
        'assigned_by_user_id',
        'note',
    ];

    public function studentCase(): BelongsTo
    {
        return $this->belongsTo(StudentCase::class);
    }

    public function fromUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function toUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }
}
