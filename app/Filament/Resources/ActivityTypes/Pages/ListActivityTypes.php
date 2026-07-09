<?php

namespace App\Filament\Resources\ActivityTypes\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\ActivityTypes\ActivityTypeResource;
use Filament\Resources\Pages\ListRecords;

class ListActivityTypes extends ListRecords
{
    use ShowsCrmPageHint;

    protected static function crmHintKey(): ?string
    {
        return 'activity.types';
    }

    protected static string $resource = ActivityTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
