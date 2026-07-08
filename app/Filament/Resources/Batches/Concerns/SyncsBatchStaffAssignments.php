<?php

namespace App\Filament\Resources\Batches\Concerns;

use App\Models\Batch;
use App\Models\CourseSubject;
use App\Services\BatchStaffAssignmentService;
use Filament\Schemas\Components\Utilities\Set;

trait SyncsBatchStaffAssignments
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFillForStaffAssignments(array $data, Batch $batch): array
    {
        return array_merge($data, app(BatchStaffAssignmentService::class)->formStateForBatch($batch));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncBatchStaffAssignments(Batch $batch, array $data): void
    {
        app(BatchStaffAssignmentService::class)->sync(
            $batch,
            filled($data['lead_teacher_user_id'] ?? null) ? (int) $data['lead_teacher_user_id'] : null,
            $data['subject_teacher_assignments'] ?? [],
        );
    }

    /**
     * @return array<int, array{course_subject_id: int, subject_name: string, user_id: null}>
     */
    protected function subjectAssignmentRowsForCourse(?int $courseId): array
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
                'subject_name' => $subject->displayLabel(),
                'user_id' => null,
            ])
            ->values()
            ->all();
    }

    protected function applyCourseSubjectRowsToForm(Set $set, ?int $courseId): void
    {
        $set('subject_teacher_assignments', $this->subjectAssignmentRowsForCourse($courseId));
    }
}
