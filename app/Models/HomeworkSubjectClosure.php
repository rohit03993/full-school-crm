<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeworkSubjectClosure extends Model
{
    public const TeacherAbsent = 'teacher_absent';

    public const NoHomework = 'no_homework';

    protected $fillable = [
        'batch_id',
        'course_subject_id',
        'homework_date',
        'reason',
        'reason_note',
        'teacher_user_id',
        'whole_subject',
        'closed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'homework_date' => 'date',
            'whole_subject' => 'boolean',
        ];
    }

    /**
     * Does this reason answer for that teacher? Old reasons and subjects without a teacher cover everyone.
     */
    public function covers(int $teacherUserId): bool
    {
        return $this->whole_subject
            || $this->teacher_user_id === null
            || (int) $this->teacher_user_id === $teacherUserId;
    }

    public function label(): string
    {
        return match ($this->reason) {
            self::TeacherAbsent => 'Teacher was absent',
            self::NoHomework => 'No homework today',
            default => 'Closed',
        };
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function courseSubject(): BelongsTo
    {
        return $this->belongsTo(CourseSubject::class);
    }
}
