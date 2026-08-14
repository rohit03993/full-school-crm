<?php

namespace App\Models;

use App\Enums\BatchShift;
use App\Enums\BatchStatus;
use App\Enums\ResultDeclarationStatus;
use App\Support\ClassSectionLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Batch extends Model
{
    protected $fillable = [
        'name',
        'section',
        'shift',
        'course_id',
        'academic_session_id',
        'trainer_user_id',
        'start_date',
        'end_date',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => BatchStatus::class,
            'shift' => BatchShift::class,
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

    public function trainer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'trainer_user_id');
    }

    public function batchStudents(): HasMany
    {
        return $this->hasMany(BatchStudent::class);
    }

    public function activeStudents(): HasMany
    {
        return $this->hasMany(BatchStudent::class)->where('is_active', true);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function staffAssignments(): HasMany
    {
        return $this->hasMany(BatchStaffAssignment::class);
    }

    /**
     * Subjects enabled for this specific section.
     *
     * CourseSubject remains the shared catalogue so existing homework/exam foreign keys stay
     * stable; batch_subjects decides which catalogue entries this section actually uses.
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(CourseSubject::class, 'batch_subjects')
            ->withPivot('sort_order')
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderBy('course_subjects.name');
    }

    public function activeSubjects(): BelongsToMany
    {
        return $this->subjects()->where('course_subjects.is_active', true);
    }

    public function isActive(): bool
    {
        return $this->status === BatchStatus::Active;
    }

    public function selectLabel(): string
    {
        return ClassSectionLabel::forBatch($this);
    }

    public function displayLabel(): string
    {
        return ClassSectionLabel::forBatch($this, includeSession: false, includeShift: false);
    }

    /**
     * @return array{can_delete: bool, reason: ?string}
     */
    public function deletionBlockReason(): array
    {
        if (Schema::hasTable('result_declarations')) {
            $publishedResults = ResultDeclaration::query()
                ->where('batch_id', $this->id)
                ->where('status', ResultDeclarationStatus::Published)
                ->count();

            if ($publishedResults > 0) {
                return [
                    'can_delete' => false,
                    'reason' => 'Published exam results exist for this section ('.$publishedResults.' test'.($publishedResults === 1 ? '' : 's').'). Mark the section Completed instead of deleting.',
                ];
            }
        }

        return ['can_delete' => true, 'reason' => null];
    }

    protected static function booted(): void
    {
        static::saving(function (Batch $batch): void {
            if (blank($batch->section) || ! $batch->course_id) {
                return;
            }

            $course = $batch->course ?? Course::query()->find($batch->course_id);

            if ($course) {
                $batch->name = ClassSectionLabel::suggestBatchName($course->name, $batch->section);
            }
        });
    }
}
