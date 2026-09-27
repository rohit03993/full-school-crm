<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StandardCourseChapter extends Model
{
    protected $fillable = [
        'standard_course_plan_id',
        'name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(StandardCoursePlan::class, 'standard_course_plan_id');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(StandardCourseTopic::class)->orderBy('sort_order')->orderBy('id');
    }

    public function plannedMinutes(): int
    {
        return (int) $this->topics->sum('planned_minutes');
    }
}
