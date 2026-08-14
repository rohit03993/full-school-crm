<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\CourseSubject;
use App\Support\CommonCourseSubjects;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns section-specific subject selection while keeping CourseSubject as the stable catalogue
 * referenced by homework, checks, staff assignments, and exam windows.
 */
class BatchSubjectService
{
    public function __construct(
        protected CourseSubjectService $courseSubjects,
        protected BatchStaffAssignmentService $staffAssignments,
    ) {}

    /**
     * @return array{
     *     lead_teacher_user_id: ?int,
     *     common_subject_presets: list<string>,
     *     section_subjects: list<array{
     *         course_subject_id: int,
     *         name: string,
     *         code: ?string,
     *         default_max_marks: ?int,
     *         is_active: bool,
     *         user_id: ?int
     *     }>
     * }
     */
    public function formStateForBatch(Batch $batch): array
    {
        $batch->loadMissing([
            'activeSubjects',
            'staffAssignments.user',
            'staffAssignments.courseSubject',
        ]);

        $staffState = $this->staffAssignments->formStateForBatch($batch);
        $teacherBySubject = collect($staffState['subject_teacher_assignments'])
            ->keyBy('course_subject_id');

        $rows = $batch->activeSubjects
            ->map(function (CourseSubject $subject) use ($teacherBySubject): array {
                $teacher = $teacherBySubject->get($subject->id);

                return [
                    'course_subject_id' => $subject->id,
                    'name' => $subject->name,
                    'code' => $subject->code,
                    'default_max_marks' => $subject->default_max_marks,
                    'is_active' => $subject->is_active,
                    'user_id' => $teacher['user_id'] ?? null,
                ];
            })
            ->values()
            ->all();

        return [
            'lead_teacher_user_id' => $staffState['lead_teacher_user_id'],
            'common_subject_presets' => CommonCourseSubjects::keysMatchingRows($rows),
            'section_subjects' => $rows,
        ];
    }

    /**
     * Save this section's selected subjects and teacher beside each subject.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    public function sync(
        Batch $batch,
        array $rows,
        ?int $leadTeacherUserId = null,
    ): void {
        $batch->loadMissing('course');

        if (! $batch->course_id) {
            throw ValidationException::withMessages([
                'section_subjects' => 'Select a programme before adding section subjects.',
            ]);
        }

        $normalized = $this->courseSubjects->normalize($rows);

        DB::transaction(function () use ($batch, $rows, $normalized, $leadTeacherUserId): void {
            $selectedIds = [];
            $teacherRows = [];

            foreach ($normalized as $index => $subjectData) {
                $source = collect($rows)->first(
                    fn (array $row): bool => mb_strtolower(trim((string) ($row['name'] ?? '')))
                        === mb_strtolower($subjectData['name'])
                ) ?? [];
                $subject = $this->resolveCatalogueSubject($batch, $subjectData, $source);
                $selectedIds[$subject->id] = ['sort_order' => $index + 1];

                if (filled($source['user_id'] ?? null)) {
                    $teacherRows[] = [
                        'course_subject_id' => $subject->id,
                        'user_id' => (int) $source['user_id'],
                    ];
                }
            }

            $batch->subjects()->sync($selectedIds);
            $this->staffAssignments->sync($batch, $leadTeacherUserId, $teacherRows);
        });
    }

    /**
     * Suggested rows when a section chooses a programme. These are only defaults for that
     * section; the user can untick them before saving without affecting another section.
     *
     * @return list<array<string, mixed>>
     */
    public function suggestedRowsForCourse(?int $courseId): array
    {
        if (! $courseId) {
            return [];
        }

        return CourseSubject::query()
            ->where('course_id', $courseId)
            ->active()
            ->ordered()
            ->get()
            ->map(fn (CourseSubject $subject): array => [
                'course_subject_id' => $subject->id,
                'name' => $subject->name,
                'code' => $subject->code,
                'default_max_marks' => $subject->default_max_marks,
                'is_active' => true,
                'user_id' => null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array{name: string, code: ?string, default_max_marks: ?int, is_active: bool}  $data
     * @param  array<string, mixed>  $source
     */
    protected function resolveCatalogueSubject(Batch $batch, array $data, array $source): CourseSubject
    {
        $subjectId = (int) ($source['course_subject_id'] ?? 0);

        if ($subjectId > 0) {
            $existing = CourseSubject::query()
                ->whereKey($subjectId)
                ->where('course_id', $batch->course_id)
                ->first();

            if ($existing) {
                if (! $existing->is_active) {
                    $existing->update(['is_active' => true]);
                }

                return $existing;
            }
        }

        $existing = CourseSubject::query()
            ->where('course_id', $batch->course_id)
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($data['name'])])
            ->first();

        if ($existing) {
            if (! $existing->is_active) {
                $existing->update(['is_active' => true]);
            }

            return $existing;
        }

        return CourseSubject::query()->create([
            'course_id' => $batch->course_id,
            'name' => $data['name'],
            'code' => $data['code'],
            'default_max_marks' => $data['default_max_marks'],
            'sort_order' => (int) CourseSubject::query()
                ->where('course_id', $batch->course_id)
                ->max('sort_order') + 1,
            'is_active' => true,
        ]);
    }
}
