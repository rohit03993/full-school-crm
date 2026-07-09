<?php

namespace App\Models;

use App\Enums\BatchShift;
use App\Enums\BatchStatus;
use App\Support\ClassSectionLabel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
