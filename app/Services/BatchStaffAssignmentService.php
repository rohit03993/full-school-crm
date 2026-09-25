<?php

namespace App\Services;

use App\Enums\BatchStaffRole;
use App\Enums\BatchStatus;
use App\Enums\CrmPermission;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\CourseSubject;
use App\Models\Student;
use App\Models\User;
use App\Support\CrmAccess;
use Illuminate\Database\Eloquent\Builder;
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
            'activeSubjects',
            'staffAssignments.user',
            'staffAssignments.courseSubject',
        ]);

        $lead = $batch->staffAssignments->first(fn (BatchStaffAssignment $row): bool => $row->isLeadTeacher());

        $assignedBySubject = $batch->staffAssignments
            ->filter(fn (BatchStaffAssignment $row): bool => $row->isSubjectTeacher())
            ->keyBy('course_subject_id');

        $subjectRows = $batch->activeSubjects
            ->map(function (CourseSubject $subject) use ($assignedBySubject): array {
                $assignment = $assignedBySubject->get($subject->id);

                return [
                    'course_subject_id' => $subject->id,
                    'subject_name' => $subject->displayLabel(),
                    'user_id' => $assignment?->user_id,
                ];
            })
            ->values()
            ->all();

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

    public function userHasTeachingAssignments(User $user): bool
    {
        return BatchStaffAssignment::query()
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * Teacher / Faculty only sees classes assigned in Class & Sections.
     * Super Admin and Academic coordinator keep the full list.
     */
    public function shouldLimitToAssignedClasses(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return false;
        }

        if (CrmAccess::can($user, CrmPermission::AcademicsManage)
            || CrmAccess::can($user, CrmPermission::HomeworkManage)) {
            return false;
        }

        return $user->hasRole(StaffJobRole::Teacher->value);
    }

    /**
     * @return list<int>|null null means no class lock
     */
    public function limitedBatchIdsFor(?User $user): ?array
    {
        if (! $this->shouldLimitToAssignedClasses($user) || ! $user) {
            return null;
        }

        return $this->assignedBatchIds($user);
    }

    /**
     * @return list<int>
     */
    public function assignedBatchIds(User $user): array
    {
        return BatchStaffAssignment::query()
            ->where('user_id', $user->id)
            ->pluck('batch_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function canAccessClass(?User $user, int $batchId): bool
    {
        if ($batchId < 1) {
            return false;
        }

        $ids = $this->limitedBatchIdsFor($user);

        if ($ids === null) {
            return true;
        }

        return in_array($batchId, $ids, true);
    }

    public function canViewStudent(?User $user, Student|int $student): bool
    {
        $studentId = $student instanceof Student ? (int) $student->id : (int) $student;

        if ($studentId < 1) {
            return false;
        }

        $query = Student::query()->whereKey($studentId);
        $this->constrainStudentsQuery($query, $user);

        return $query->exists();
    }

    /**
     * @return array<int, string>
     */
    public function activeBatchOptionsFor(?User $user): array
    {
        $query = Batch::query()
            ->where('status', BatchStatus::Active)
            ->with('course')
            ->orderBy('name');

        $ids = $this->limitedBatchIdsFor($user);

        if ($ids !== null) {
            if ($ids === []) {
                return [];
            }

            $query->whereIn('id', $ids);
        }

        return $query
            ->get()
            ->mapWithKeys(fn (Batch $batch): array => [
                $batch->id => filled($batch->course?->name)
                    ? "{$batch->name} · {$batch->course->name}"
                    : (string) $batch->name,
            ])
            ->all();
    }

    public function constrainStudentsQuery(Builder $query, ?User $user): Builder
    {
        $ids = $this->limitedBatchIdsFor($user);

        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereHas(
            'batchStudents',
            fn (Builder $batchStudents): Builder => $batchStudents
                ->where('is_active', true)
                ->whereIn('batch_id', $ids),
        );
    }

    public function constrainBatchColumn(Builder $query, ?User $user, string $column = 'batch_id'): Builder
    {
        $ids = $this->limitedBatchIdsFor($user);

        if ($ids === null) {
            return $query;
        }

        if ($ids === []) {
            return $query->whereRaw('0 = 1');
        }

        return $query->whereIn($column, $ids);
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

        $validSubjectIds = $batch->activeSubjects()
            ->pluck('course_subjects.id')
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
                    'subject_teacher_assignments' => 'One or more subjects are not selected for this section.',
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
