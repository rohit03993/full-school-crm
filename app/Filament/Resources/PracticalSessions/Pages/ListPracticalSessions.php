<?php

namespace App\Filament\Resources\PracticalSessions\Pages;

use App\Filament\Resources\PracticalSessions\PracticalSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPracticalSessions extends ListRecords
{
    protected static string $resource = PracticalSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add Practical'),
        ];
    }
}
