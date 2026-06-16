<?php

namespace Database\Seeders;

use App\Enums\CourseStatus;
use App\Enums\CourseType;
use App\Enums\DurationType;
use App\Models\Course;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $courses = [
            [
                'name' => 'Course Not Decided',
                'code' => 'GEN-UNDECIDED',
                'course_type' => CourseType::Custom,
                'duration' => 1,
                'duration_type' => DurationType::Months,
                'fee' => 0,
                'description' => 'Placeholder for walk-in enquiries when course interest is not yet confirmed.',
            ],
            [
                'name' => 'BSc in Hotel Management',
                'code' => 'BSC-HM-2Y',
                'course_type' => CourseType::Bsc,
                'duration' => 2,
                'duration_type' => DurationType::Years,
                'fee' => 0,
                'description' => 'Bachelor of Science in Hotel Management — 2 year programme.',
            ],
            [
                'name' => 'BSc in Hotel Management',
                'code' => 'BSC-HM-3Y',
                'course_type' => CourseType::Bsc,
                'duration' => 3,
                'duration_type' => DurationType::Years,
                'fee' => 0,
                'description' => 'Bachelor of Science in Hotel Management — 3 year programme.',
            ],
            [
                'name' => 'Diploma in Hotel Management',
                'code' => 'DIP-HM-6M',
                'course_type' => CourseType::Diploma,
                'duration' => 6,
                'duration_type' => DurationType::Months,
                'fee' => 0,
                'description' => 'Diploma in Hotel Management — 6 month programme.',
            ],
            [
                'name' => 'Diploma in Hotel Management',
                'code' => 'DIP-HM-3M',
                'course_type' => CourseType::Diploma,
                'duration' => 3,
                'duration_type' => DurationType::Months,
                'fee' => 0,
                'description' => 'Diploma in Hotel Management — 3 month programme.',
            ],
        ];

        foreach ($courses as $course) {
            Course::query()->updateOrCreate(
                ['code' => $course['code']],
                [
                    ...$course,
                    'status' => CourseStatus::Active,
                ],
            );
        }
    }
}
