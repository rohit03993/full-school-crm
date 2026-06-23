<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BatchService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    public function assign(Student $student, Batch $batch, User $staff): BatchStudent
    {
        if (! $batch->isActive()) {
            throw ValidationException::withMessages([
                'batch_id' => 'Cannot assign students to a completed batch.',
            ]);
        }

        $this->assertBatchMatchesEnrollment($student, $batch);

        return DB::transaction(function () use ($student, $batch, $staff): BatchStudent {
            $previous = BatchStudent::query()
                ->where('student_id', $student->id)
                ->where('is_active', true)
                ->with('batch')
                ->first();

            if ($previous?->batch_id === $batch->id) {
                return $previous;
            }

            if ($previous) {
                $previous->update(['is_active' => false]);
            }

            $assignment = BatchStudent::query()->create([
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'assigned_at' => now(),
                'is_active' => true,
                'assigned_by_user_id' => $staff->id,
            ]);

            $action = $previous ? 'batch_reassigned' : 'batch_assigned';

            $this->audit->log(
                $action,
                $assignment,
                $previous ? [
                    'batch_id' => $previous->batch_id,
                    'batch_name' => $previous->batch?->name,
                ] : null,
                [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'batch_id' => $batch->id,
                    'batch_name' => $batch->name,
                ],
                user: $staff,
            );

            return $assignment->load('batch');
        });
    }

    /**
     * @param  array<int, int>  $studentIds
     * @return int Number of students newly assigned or reassigned
     */
    public function bulkAssign(Batch $batch, array $studentIds, User $staff): int
    {
        $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
        $assigned = 0;

        foreach ($studentIds as $studentId) {
            $student = Student::query()->find($studentId);

            if (! $student) {
                continue;
            }

            $existing = BatchStudent::query()
                ->where('student_id', $student->id)
                ->where('is_active', true)
                ->where('batch_id', $batch->id)
                ->exists();

            if ($existing) {
                continue;
            }

            $this->assign($student, $batch, $staff);
            $assigned++;
        }

        return $assigned;
    }

    protected function assertBatchMatchesEnrollment(Student $student, Batch $batch): void
    {
        $enrollment = $student->activeEnrollment;

        if (! $enrollment) {
            return;
        }

        if ($enrollment->course_id !== $batch->course_id) {
            throw ValidationException::withMessages([
                'batch_id' => 'This batch belongs to a different course than the student’s active enrollment.',
            ]);
        }

        if (
            $enrollment->academic_session_id !== null
            && $batch->academic_session_id !== null
            && $enrollment->academic_session_id !== $batch->academic_session_id
        ) {
            throw ValidationException::withMessages([
                'batch_id' => 'This batch belongs to a different academic session than the student’s enrollment.',
            ]);
        }
    }
}
