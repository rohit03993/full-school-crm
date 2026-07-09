<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamWindowSubject extends Model
{
    protected $fillable = [
        'exam_window_id',
        'course_subject_id',
        'max_marks',
        'activity_session_id',
        'entered_by_user_id',
        'marks_entered_at',
    ];

    protected function casts(): array
    {
        return [
            'max_marks' => 'integer',
            'marks_entered_at' => 'datetime',
        ];
    }

    public function examWindow(): BelongsTo
    {
        return $this->belongsTo(ExamWindow::class);
    }

    public function courseSubject(): BelongsTo
    {
        return $this->belongsTo(CourseSubject::class);
    }

    public function activitySession(): BelongsTo
    {
        return $this->belongsTo(ActivitySession::class);
    }

    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }

    public function hasMarksEntered(): bool
    {
        return $this->marks_entered_at !== null;
    }
}
