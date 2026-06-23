<?php

namespace App\Filament\Resources\Batches\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\Batches\BatchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBatch extends CreateRecord
{
    use ShowsCrmPageHint;

    protected static function crmHintKey(): ?string
    {
        return 'batches.create';
    }

    protected static string $resource = BatchResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
