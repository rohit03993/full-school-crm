<?php

namespace App\Services;

use App\Enums\CourseStatus;
use App\Enums\InstituteType;
use App\Enums\ProgrammeCategory;
use App\Models\Course;
use App\Support\DefaultProgrammes;
use App\Support\InstituteProfile;
use App\Support\InstituteSettings;
use App\Support\SiteContent;

class InstituteProfileService
{
    /**
     * @return array{changed: bool, type: InstituteType, programmes_seeded: int}
     */
    public function apply(InstituteType $type): array
    {
        $previous = InstituteProfile::type();

        if ($previous === $type) {
            return [
                'changed' => false,
                'type' => $type,
                'programmes_seeded' => 0,
            ];
        }

        InstituteProfile::setType($type);
        $programmesSeeded = $this->syncProgrammes($type);

        SiteContent::clearCache();
        InstituteSettings::clearCache();

        return [
            'changed' => true,
            'type' => $type,
            'programmes_seeded' => $programmesSeeded,
        ];
    }

    public function syncProgrammesForType(InstituteType $type): int
    {
        return $this->syncProgrammes($type);
    }

    protected function syncProgrammes(InstituteType $type): int
    {
        $count = 0;

        foreach (DefaultProgrammes::internal() as $course) {
            $this->upsertCourse($course);
            $count++;
        }

        foreach (DefaultProgrammes::forType($type) as $course) {
            $this->upsertCourse($course);
            $count++;
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $course
     */
    protected function upsertCourse(array $course): void
    {
        Course::query()->updateOrCreate(
            ['code' => $course['code']],
            [
                ...$course,
                'status' => CourseStatus::Active,
            ],
        );
    }
}
