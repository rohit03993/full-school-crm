<?php

namespace App\Filament\Resources\Batches\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Pages\ClassSectionsPage;
use App\Filament\Resources\Batches\BatchResource;
use App\Filament\Resources\Batches\Concerns\SyncsBatchStaffAssignments;
use Filament\Resources\Pages\CreateRecord;

class CreateBatch extends CreateRecord
{
    use ShowsCrmPageHint;
    use SyncsBatchStaffAssignments;

    protected static function crmHintKey(): ?string
    {
        return 'batches.create';
    }

    protected static string $resource = BatchResource::class;

    protected function getRedirectUrl(): string
    {
        return ClassSectionsPage::getUrl();
    }

    protected function afterCreate(): void
    {
        $this->syncBatchStaffAssignments($this->record, $this->form->getState());
    }
}
