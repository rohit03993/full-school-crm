<?php

namespace App\Filament\Resources\ActivitySessions\Pages;

use App\Filament\Resources\ActivitySessions\ActivitySessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActivitySessions extends ListRecords
{
    protected static string $resource = ActivitySessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Activity'),
        ];
    }
}
