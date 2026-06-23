<?php

namespace App\Support;

use App\Enums\CourseStatus;
use App\Enums\ProgrammeCategory;
use App\Enums\DurationType;
use App\Models\Course;

class DefaultCourse
{
    public const UNDECIDED_CODE = 'GEN-UNDECIDED';

    public static function undecided(): Course
    {
        return Course::query()->firstOrCreate(
            ['code' => self::UNDECIDED_CODE],
            [
                'name' => 'Course Not Decided',
                'programme_category' => ProgrammeCategory::Custom,
                'duration' => 1,
                'duration_type' => DurationType::Months,
                'fee' => 0,
                'status' => CourseStatus::Active,
                'show_on_website' => false,
                'description' => 'Placeholder for walk-in enquiries when course interest is not yet confirmed.',
            ],
        );
    }
}
