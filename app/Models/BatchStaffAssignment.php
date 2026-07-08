<?php

namespace App\Models;

use App\Enums\BatchStaffRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BatchStaffAssignment extends Model
{
    protected $fillable = [
        'batch_id',
        'user_id',
        'role',
        'course_subject_id',
    ];

    protected function casts(): array
    {
        return [
            'role' => BatchStaffRole::class,
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function courseSubject(): BelongsTo
    {
        return $this->belongsTo(CourseSubject::class);
    }

    public function isLeadTeacher(): bool
    {
        return $this->role === BatchStaffRole::LeadTeacher;
    }

    public function isSubjectTeacher(): bool
    {
        return $this->role === BatchStaffRole::SubjectTeacher;
    }
}
