<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CourseSubject extends Model
{
    protected $fillable = [
        'course_id',
        'name',
        'code',
        'default_max_marks',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_max_marks' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function batchStaffAssignments(): HasMany
    {
        return $this->hasMany(BatchStaffAssignment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function displayLabel(): string
    {
        if (filled($this->code)) {
            return "{$this->name} ({$this->code})";
        }

        return $this->name;
    }
}
