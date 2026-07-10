<?php

namespace App\Models;

use App\Enums\CampusVisitPurpose;
use App\Enums\StudentCaseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StudentCase extends Model
{
    protected $fillable = [
        'case_number',
        'student_id',
        'visit_id',
        'case_type',
        'status',
        'title',
        'summary',
        'opened_by_user_id',
        'current_assignee_user_id',
        'closed_by_user_id',
        'closing_note',
        'opened_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'case_type' => CampusVisitPurpose::class,
            'status' => StudentCaseStatus::class,
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by_user_id');
    }

    public function currentAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_assignee_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(StudentCaseAssignment::class)->orderBy('id');
    }

    public function calls(): HasMany
    {
        return $this->hasMany(StudentCall::class)->orderByDesc('called_at');
    }

    public function isOpen(): bool
    {
        return $this->status === StudentCaseStatus::Open;
    }
}
