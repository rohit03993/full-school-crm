<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SectionCourseTopic extends Model
{
    protected $fillable = [
        'section_course_chapter_id',
        'name',
        'planned_minutes',
        'reference_book',
        'dpp_count',
        'quiz_count',
        'test_count',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'planned_minutes' => 'integer',
            'dpp_count' => 'integer',
            'quiz_count' => 'integer',
            'test_count' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function chapter(): BelongsTo
    {
        return $this->belongsTo(SectionCourseChapter::class, 'section_course_chapter_id');
    }
}
