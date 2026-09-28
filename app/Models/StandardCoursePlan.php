<?php

namespace App\Models;

use App\Enums\StandardCoursePlanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class StandardCoursePlan extends Model
{
    protected $fillable = [
        'academic_session_id',
        'course_id',
        'course_subject_id',
        'lecture_minutes',
        'status',
        'ready_at',
        'ready_by_user_id',
        'created_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'lecture_minutes' => 'integer',
            'status' => StandardCoursePlanStatus::class,
            'ready_at' => 'datetime',
        ];
    }

    public function academicSession(): BelongsTo
    {
        return $this->belongsTo(AcademicSession::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function courseSubject(): BelongsTo
    {
        return $this->belongsTo(CourseSubject::class);
    }

    public function readyBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ready_by_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(StandardCourseChapter::class)->orderBy('sort_order')->orderBy('id');
    }

    public function practicals(): HasMany
    {
        return $this->hasMany(StandardCoursePractical::class)->orderBy('sort_order')->orderBy('id');
    }

    public function topics(): HasManyThrough
    {
        return $this->hasManyThrough(StandardCourseTopic::class, StandardCourseChapter::class);
    }

    public function totalPlannedMinutes(): int
    {
        return (int) $this->topics()->sum('standard_course_topics.planned_minutes');
    }

    public static function teachingDays(int $plannedMinutes, int $lectureMinutes): int
    {
        $lectureMinutes = max(1, $lectureMinutes);

        if ($plannedMinutes < 1) {
            return 0;
        }

        return (int) ceil($plannedMinutes / $lectureMinutes);
    }
}
