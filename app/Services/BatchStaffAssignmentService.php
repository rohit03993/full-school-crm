<?php

namespace App\Services;

use App\Enums\BatchStaffRole;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\CourseSubject;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class BatchStaffAssignmentService
{
    /**
     * @param  array<int, array{course_subject_id?: mixed, user_id?: mixed}>  $subjectRows
     */
    public function sync(Batch $batch, ?int $leadTeacherUserId, array $subjectRows): void
    {
        $batch->loadMissing('course');

        if ($leadTeacherUserId) {
            $this->assertActiveStaff($leadTeacherUserId, 'lead_teacher_user_id');
        }

        $normalizedSubjects = $this->normalizeSubjectRows($batch, $subjectRows);

        BatchStaffAssignment::query()->where('batch_id', $batch->id)->delete();

        if ($leadTeacherUserId) {
            BatchStaffAssignment::query()->create([
                'batch_id' => $batch->id,
                'user_id' => $leadTeacherUserId,
                'role' => BatchStaffRole::LeadTeacher,
                'course_subject_id' => null,
            ]);
        }

        foreach ($normalizedSubjects as $row) {
            BatchStaffAssignment::query()->create([
                'batch_id' => $batch->id,
                'user_id' => $row['user_id'],
                'role' => BatchStaffRole::SubjectTeacher,
                'course_subject_id' => $row['course_subject_id'],
            ]);
        }
    }

    /**
     * @return array{
     *     lead_teacher_user_id: ?int,
     *     subject_teacher_assignments: array<int, array{course_subject_id: int, subject_name: string, user_id: ?int}>
     * }
     */
    public function formStateForBatch(Batch $batch): array
    {
        $batch->loadMissing([
            'course.subjects' => fn ($query) => $query->ordered(),
            'staffAssignments.user',
            'staffAssignments.courseSubject',
        ]);

        $lead = $batch->staffAssignments->first(fn (BatchStaffAssignment $row): bool => $row->isLeadTeacher());

        $assignedBySubject = $batch->staffAssignments
            ->filter(fn (BatchStaffAssignment $row): bool => $row->isSubjectTeacher())
            ->keyBy('course_subject_id');

        $subjectRows = $batch->course?->subjects
            ->map(function (CourseSubject $subject) use ($assignedBySubject): array {
                $assignment = $assignedBySubject->get($subject->id);

                return [
                    'course_subject_id' => $subject->id,
                    'subject_name' => $subject->displayLabel(),
                    'user_id' => $assignment?->user_id,
                ];
            })
            ->values()
            ->all() ?? [];

        return [
            'lead_teacher_user_id' => $lead?->user_id,
            'subject_teacher_assignments' => $subjectRows,
        ];
    }

    /**
     * @return array<int, array{
     *     batch: Batch,
     *     role: BatchStaffRole,
     *     course_subject: ?CourseSubject,
     * }>
     */
    public function assignmentsForUser(User $user): array
    {
        $rows = BatchStaffAssignment::query()
            ->where('user_id', $user->id)
            ->with([
                'batch.course',
                'batch.academicSession',
                'courseSubject',
            ])
            ->get()
            ->sortBy(fn (BatchStaffAssignment $row): string => $row->batch?->name ?? '')
            ->values();

        return $rows->map(fn (BatchStaffAssignment $row): array => [
            'batch' => $row->batch,
            'role' => $row->role,
            'course_subject' => $row->courseSubject,
        ])->all();
    }

    /**
     * @param  array<int, array{course_subject_id?: mixed, user_id?: mixed}>  $rows
     * @return array<int, array{user_id: int, course_subject_id: int}>
     */
    protected function normalizeSubjectRows(Batch $batch, array $rows): array
    {
        if (! $batch->course_id) {
            return [];
        }

        $validSubjectIds = CourseSubject::query()
            ->where('course_id', $batch->course_id)
            ->where('is_active', true)
            ->pluck('id')
            ->all();

        $normalized = [];
        $seenSubjects = [];

        foreach ($rows as $row) {
            $subjectId = (int) ($row['course_subject_id'] ?? 0);
            $userId = filled($row['user_id'] ?? null) ? (int) $row['user_id'] : null;

            if ($subjectId <= 0 || $userId === null) {
                continue;
            }

            if (! in_array($subjectId, $validSubjectIds, true)) {
                throw ValidationException::withMessages([
                    'subject_teacher_assignments' => 'One or more subjects do not belong to this programme.',
                ]);
            }

            if (isset($seenSubjects[$subjectId])) {
                continue;
            }

            $seenSubjects[$subjectId] = true;
            $this->assertActiveStaff($userId, 'subject_teacher_assignments');

            $normalized[] = [
                'user_id' => $userId,
                'course_subject_id' => $subjectId,
            ];
        }

        return $normalized;
    }

    protected function assertActiveStaff(int $userId, string $field): void
    {
        $exists = User::query()
            ->whereKey($userId)
            ->where('is_active', true)
            ->exists();

        if (! $exists) {
            throw ValidationException::withMessages([
                $field => 'Selected staff member is inactive or not found.',
            ]);
        }
    }
}
