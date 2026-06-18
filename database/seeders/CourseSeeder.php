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
                'name' => 'Class 11 Science',
                'code' => 'SCH-11-SCI',
                'course_type' => CourseType::Bsc,
                'duration' => 1,
                'duration_type' => DurationType::Years,
                'fee' => 0,
                'description' => 'Class 11 Science stream — full academic year programme.',
            ],
            [
                'name' => 'Class 12 Commerce',
                'code' => 'SCH-12-COM',
                'course_type' => CourseType::Bsc,
                'duration' => 1,
                'duration_type' => DurationType::Years,
                'fee' => 0,
                'description' => 'Class 12 Commerce stream — full academic year programme.',
            ],
            [
                'name' => 'JEE Foundation',
                'code' => 'COACH-JEE-1Y',
                'course_type' => CourseType::Diploma,
                'duration' => 12,
                'duration_type' => DurationType::Months,
                'fee' => 0,
                'description' => 'One-year foundation coaching for engineering entrance exams.',
            ],
            [
                'name' => 'NEET Foundation',
                'code' => 'COACH-NEET-1Y',
                'course_type' => CourseType::Diploma,
                'duration' => 12,
                'duration_type' => DurationType::Months,
                'fee' => 0,
                'description' => 'One-year foundation coaching for medical entrance exams.',
            ],
            [
                'name' => 'Diploma in Computer Applications',
                'code' => 'DIP-DCA-6M',
                'course_type' => CourseType::Diploma,
                'duration' => 6,
                'duration_type' => DurationType::Months,
                'fee' => 0,
                'description' => 'Six-month diploma in computer applications and office skills.',
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
