<?php

namespace App\Filament\Resources\Courses\Concerns;

use App\Models\Course;
use App\Models\CourseSubject;
use App\Services\CourseSubjectService;
use App\Support\CommonCourseSubjects;

trait SyncsCourseSubjects
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFillForCourseSubjects(array $data, Course $course): array
    {
        $course->loadMissing(['subjects' => fn ($query) => $query->ordered()]);

        $data['course_subjects'] = $course->subjects->map(fn (CourseSubject $subject): array => [
            'name' => $subject->name,
            'code' => $subject->code,
            'default_max_marks' => $subject->default_max_marks,
            'is_active' => $subject->is_active,
        ])->values()->all();

        $data['common_subject_presets'] = CommonCourseSubjects::keysMatchingRows($data['course_subjects']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncCourseSubjects(Course $course, array $data): void
    {
        app(CourseSubjectService::class)->sync(
            $course,
            $data['course_subjects'] ?? [],
        );
    }
}
