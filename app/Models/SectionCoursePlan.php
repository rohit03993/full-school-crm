<?php

namespace App\Models;

use App\Enums\BatchStaffRole;
use App\Enums\SectionCoursePlanStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class SectionCoursePlan extends Model
{
    protected $fillable = [
        'standard_course_plan_id',
        'batch_id',
        'academic_session_id',
        'course_id',
        'course_subject_id',
        'lecture_minutes',
        'status',
        'version',
        'submitted_at',
        'submitted_by_user_id',
        'finalized_at',
        'finalized_by_user_id',
        'review_comment',
        'change_reason',
        'copied_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'lecture_minutes' => 'integer',
            'version' => 'integer',
            'status' => SectionCoursePlanStatus::class,
            'submitted_at' => 'datetime',
            'finalized_at' => 'datetime',
        ];
    }

    public function standardPlan(): BelongsTo
    {
        return $this->belongsTo(StandardCoursePlan::class, 'standard_course_plan_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
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

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function finalizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by_user_id');
    }

    public function copiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'copied_by_user_id');
    }

    public function chapters(): HasMany
    {
        return $this->hasMany(SectionCourseChapter::class)->orderBy('sort_order')->orderBy('id');
    }

    public function practicals(): HasMany
    {
        return $this->hasMany(SectionCoursePractical::class)->orderBy('sort_order')->orderBy('id');
    }

    public function topics(): HasManyThrough
    {
        return $this->hasManyThrough(SectionCourseTopic::class, SectionCourseChapter::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(SectionCoursePlanVersion::class)->orderByDesc('version');
    }

    public function subjectTeacherName(): string
    {
        $this->loadMissing('batch.staffAssignments.user');

        $assignment = $this->batch?->staffAssignments
            ->first(fn (BatchStaffAssignment $row): bool => $row->role === BatchStaffRole::SubjectTeacher
                && (int) $row->course_subject_id === (int) $this->course_subject_id);

        $name = $assignment?->user?->name;

        return filled($name) ? (string) $name : 'Not assigned';
    }

    /**
     * @return array{chapters: list<array<string, mixed>>, practicals: list<array<string, mixed>>}
     */
    public function snapshot(): array
    {
        $this->loadMissing(['chapters.topics', 'practicals']);

        return [
            'chapters' => $this->chapters->map(fn (SectionCourseChapter $chapter): array => [
                'name' => $chapter->name,
                'estimated_marks' => $chapter->estimated_marks,
                'sort_order' => $chapter->sort_order,
                'topics' => $chapter->topics->map(fn (SectionCourseTopic $topic): array => [
                    'name' => $topic->name,
                    'planned_minutes' => $topic->planned_minutes,
                    'reference_book' => $topic->reference_book,
                    'dpp_count' => $topic->dpp_count,
                    'quiz_count' => $topic->quiz_count,
                    'test_count' => $topic->test_count,
                    'sort_order' => $topic->sort_order,
                ])->values()->all(),
            ])->values()->all(),
            'practicals' => $this->practicals->map(fn (SectionCoursePractical $practical): array => [
                'name' => $practical->name,
                'kind' => $practical->kind?->value,
                'planned_minutes' => $practical->planned_minutes,
                'estimated_marks' => $practical->estimated_marks,
                'sort_order' => $practical->sort_order,
            ])->values()->all(),
        ];
    }
}
