<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionCoursePlanVersion extends Model
{
    protected $fillable = [
        'section_course_plan_id',
        'version',
        'lecture_minutes',
        'change_reason',
        'snapshot',
        'submitted_by_user_id',
        'finalized_by_user_id',
        'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'lecture_minutes' => 'integer',
            'snapshot' => 'array',
            'finalized_at' => 'datetime',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SectionCoursePlan::class, 'section_course_plan_id');
    }
}
