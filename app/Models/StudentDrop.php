<?php

namespace App\Models;

use App\Enums\InactiveReason;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDrop extends Model
{
    protected $fillable = [
        'student_id',
        'enrollment_id',
        'batch_id',
        'reason',
        'reason_type',
        'course_subject_id',
        'subject_name',
        'subject_teacher_user_id',
        'subject_teacher_name',
        'dropped_by_user_id',
        'dropped_at',
        'reactivated_by_user_id',
        'reactivated_at',
    ];

    protected function casts(): array
    {
        return [
            'reason_type' => InactiveReason::class,
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

    public function courseSubject(): BelongsTo
    {
        return $this->belongsTo(CourseSubject::class);
    }

    public function subjectTeacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_teacher_user_id');
    }

    public function inactiveTimelineTitle(): string
    {
        $title = 'Marked inactive';

        if (! $this->reason_type instanceof InactiveReason) {
            return $title;
        }

        $title .= ' · '.$this->reason_type->label();

        if ($this->reason_type === InactiveReason::Academics && filled($this->subject_name)) {
            $teacher = filled($this->subject_teacher_name)
                ? $this->subject_teacher_name
                : 'No teacher assigned';
            $title .= ' · '.$this->subject_name.' · '.$teacher;
        }

        return $title;
    }

    public function reasonLine(): string
    {
        $parts = [];

        if ($this->reason_type instanceof InactiveReason) {
            $parts[] = $this->reason_type->label();
        }

        if ($this->reason_type === InactiveReason::Academics && filled($this->subject_name)) {
            $parts[] = $this->subject_name;
            $parts[] = filled($this->subject_teacher_name)
                ? $this->subject_teacher_name
                : 'No teacher assigned';
        }

        if (filled($this->reason)) {
            $parts[] = $this->reason;
        }

        return implode(' · ', $parts);
    }
}
