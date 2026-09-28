<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SectionCourseChapter extends Model
{
    protected $fillable = [
        'section_course_plan_id',
        'name',
        'estimated_marks',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'estimated_marks' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SectionCoursePlan::class, 'section_course_plan_id');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(SectionCourseTopic::class)->orderBy('sort_order')->orderBy('id');
    }
}
