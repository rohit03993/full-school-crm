<?php

namespace App\Models;

use App\Enums\BatchShift;
use App\Enums\BatchStatus;
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

    public function isActive(): bool
    {
        return $this->status === BatchStatus::Active;
    }

    public function selectLabel(): string
    {
        $course = $this->course;

        if (! $course) {
            return $this->name;
        }

        $parts = array_filter([
            $this->name,
            $this->section,
            $this->shift?->label(),
            $course->name,
            $this->academicSession?->name,
        ]);

        return implode(' · ', $parts);
    }
}
