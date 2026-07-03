<?php

namespace App\Models;

use App\Enums\VisitMeetingAssignmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VisitMeetingAssignment extends Model
{
    protected $fillable = [
        'student_id',
        'enquiry_id',
        'assigned_to_user_id',
        'assigned_by_user_id',
        'handoff_notes',
        'meeting_notes',
        'status',
        'closed_at',
        'closed_by_user_id',
        'resulting_visit_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => VisitMeetingAssignmentStatus::class,
            'closed_at' => 'datetime',
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

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by_user_id');
    }

    public function resultingVisit(): BelongsTo
    {
        return $this->belongsTo(Visit::class, 'resulting_visit_id');
    }

    public function isOpen(): bool
    {
        return $this->status === VisitMeetingAssignmentStatus::Open;
    }
}
