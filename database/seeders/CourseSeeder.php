<?php

namespace Database\Seeders;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Support\DefaultProgrammes;
use App\Support\InstituteProfile;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    public function run(): void
    {
        $type = InstituteProfile::type();

        foreach ([...DefaultProgrammes::internal(), ...DefaultProgrammes::forType($type)] as $course) {
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
