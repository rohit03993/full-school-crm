<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseSubject;
use Illuminate\Validation\ValidationException;

class CourseSubjectService
{
    /**
     * @param  array<int, array{name?: string, code?: ?string, default_max_marks?: mixed, is_active?: bool}>  $rows
     */
    public function sync(Course $course, array $rows): void
    {
        $normalized = $this->normalize($rows);
        $keepIds = [];

        foreach ($normalized as $row) {
            $subject = CourseSubject::query()->updateOrCreate(
                [
                    'course_id' => $course->id,
                    'name' => $row['name'],
                ],
                [
                    'code' => $row['code'],
                    'default_max_marks' => $row['default_max_marks'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => $row['is_active'],
                ],
            );

            $keepIds[] = $subject->id;
        }

        $staleQuery = CourseSubject::query()->where('course_id', $course->id);

        if ($keepIds !== []) {
            $staleQuery->whereNotIn('id', $keepIds);
        }

        $staleQuery->delete();
    }

    /**
     * @param  array<int, array{name?: string, code?: ?string, default_max_marks?: mixed, is_active?: bool}>  $rows
     * @return array<int, array{name: string, code: ?string, default_max_marks: ?int, sort_order: int, is_active: bool}>
     */
    public function normalize(array $rows): array
    {
        $normalized = [];
        $seenNames = [];

        foreach (array_values($rows) as $index => $row) {
            $name = trim((string) ($row['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $nameKey = mb_strtolower($name);

            if (isset($seenNames[$nameKey])) {
                throw ValidationException::withMessages([
                    'course_subjects' => "Duplicate subject name “{$name}”.",
                ]);
            }

            $seenNames[$nameKey] = true;

            $code = filled($row['code'] ?? null) ? strtoupper(trim((string) $row['code'])) : null;
            $maxMarks = filled($row['default_max_marks'] ?? null)
                ? max(1, (int) $row['default_max_marks'])
                : null;

            $normalized[] = [
                'name' => $name,
                'code' => $code,
                'default_max_marks' => $maxMarks,
                'sort_order' => $index + 1,
                'is_active' => (bool) ($row['is_active'] ?? true),
            ];
        }

        return $normalized;
    }
}
