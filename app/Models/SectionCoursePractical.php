<?php

namespace App\Models;

use App\Enums\StandardCoursePracticalKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionCoursePractical extends Model
{
    protected $fillable = [
        'section_course_plan_id',
        'name',
        'kind',
        'planned_minutes',
        'estimated_marks',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'kind' => StandardCoursePracticalKind::class,
            'planned_minutes' => 'integer',
            'estimated_marks' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SectionCoursePlan::class, 'section_course_plan_id');
    }
}
