<?php

namespace Database\Seeders;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Support\DefaultProgrammes;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (DefaultProgrammes::internal() as $course) {
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
