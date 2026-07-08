<?php

namespace App\Services;

use App\Enums\BatchShift;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\DurationType;
use App\Enums\ProgrammeCategory;
use App\Models\Batch;
use App\Models\Course;
use App\Support\ClassSectionLabel;
use App\Support\DefaultCourse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ClassSectionService
{
    public function __construct(
        protected CourseSubjectService $courseSubjects,
        protected BatchStaffAssignmentService $staffAssignments,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     * @return array{course: Course, batch: Batch}
     */
    public function create(array $data): array
    {
        return DB::transaction(function () use ($data): array {
            $course = $this->resolveCourse($data);
            $batch = $this->createBatch($course, $data);

            if (($data['programme_mode'] ?? 'existing') === 'new' && ! empty($data['course_subjects'])) {
                $this->courseSubjects->sync($course, $data['course_subjects']);
            }

            $this->staffAssignments->sync(
                $batch,
                filled($data['lead_teacher_user_id'] ?? null) ? (int) $data['lead_teacher_user_id'] : null,
                $data['subject_teacher_assignments'] ?? [],
            );

            return [
                'course' => $course->fresh(['subjects']),
                'batch' => $batch->fresh(['course', 'academicSession', 'staffAssignments.user']),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function resolveCourse(array $data): Course
    {
        if (($data['programme_mode'] ?? 'existing') === 'existing') {
            $courseId = (int) ($data['course_id'] ?? 0);

            if ($courseId <= 0) {
                throw ValidationException::withMessages([
                    'course_id' => 'Select an existing programme/class.',
                ]);
            }

            $course = Course::query()->find($courseId);

            if (! $course || $course->code === DefaultCourse::UNDECIDED_CODE) {
                throw ValidationException::withMessages([
                    'course_id' => 'Select a valid programme/class.',
                ]);
            }

            return $course;
        }

        $name = trim((string) ($data['programme_name'] ?? ''));

        if ($name === '') {
            throw ValidationException::withMessages([
                'programme_name' => 'Programme / class name is required.',
            ]);
        }

        $code = strtoupper(trim((string) ($data['programme_code'] ?? ClassSectionLabel::suggestCourseCode($name))));

        if (Course::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages([
                'programme_code' => 'This programme code is already in use.',
            ]);
        }

        return Course::query()->create([
            'name' => $name,
            'code' => $code,
            'programme_category' => ProgrammeCategory::Custom,
            'duration' => max(1, (int) ($data['duration'] ?? 1)),
            'duration_type' => DurationType::tryFrom((string) ($data['duration_type'] ?? '')) ?? DurationType::Years,
            'fee' => max(0, (float) ($data['fee'] ?? 0)),
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'status' => CourseStatus::Active,
            'show_on_website' => (bool) ($data['show_on_website'] ?? true),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function createBatch(Course $course, array $data): Batch
    {
        $sessionId = (int) ($data['academic_session_id'] ?? 0);
        $trainerId = (int) ($data['trainer_user_id'] ?? 0);
        $section = trim((string) ($data['section'] ?? ''));

        if ($sessionId <= 0) {
            throw ValidationException::withMessages([
                'academic_session_id' => 'Academic session is required.',
            ]);
        }

        if ($trainerId <= 0) {
            throw ValidationException::withMessages([
                'trainer_user_id' => 'Select faculty / trainer for attendance and batch ownership.',
            ]);
        }

        if ($section === '') {
            throw ValidationException::withMessages([
                'section' => 'Section / batch label is required (e.g. A, B, Morning).',
            ]);
        }

        $duplicate = Batch::query()
            ->where('course_id', $course->id)
            ->where('academic_session_id', $sessionId)
            ->where('section', $section)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'section' => "Section “{$section}” already exists for {$course->name} in this session.",
            ]);
        }

        $batchName = trim((string) ($data['batch_name'] ?? ''));

        if ($batchName === '') {
            $batchName = ClassSectionLabel::suggestBatchName($course->name, $section);
        }

        return Batch::query()->create([
            'name' => $batchName,
            'section' => $section,
            'shift' => filled($data['shift'] ?? null) ? BatchShift::tryFrom((string) $data['shift']) : null,
            'course_id' => $course->id,
            'academic_session_id' => $sessionId,
            'trainer_user_id' => $trainerId,
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'status' => BatchStatus::Active,
        ]);
    }
}
