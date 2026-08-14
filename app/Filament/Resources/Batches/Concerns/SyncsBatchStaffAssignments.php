<?php

namespace App\Filament\Resources\Batches\Concerns;

use App\Models\Batch;
use App\Services\BatchSubjectService;

trait SyncsBatchStaffAssignments
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFillForStaffAssignments(array $data, Batch $batch): array
    {
        return array_merge($data, app(BatchSubjectService::class)->formStateForBatch($batch));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function syncBatchStaffAssignments(Batch $batch, array $data): void
    {
        app(BatchSubjectService::class)->sync(
            $batch,
            $data['section_subjects'] ?? [],
            filled($data['lead_teacher_user_id'] ?? null) ? (int) $data['lead_teacher_user_id'] : null,
        );
    }
}
