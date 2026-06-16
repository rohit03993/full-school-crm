<?php

namespace App\Filament\Resources\Seminars\Pages;

use App\Filament\Resources\Seminars\SeminarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSeminars extends ListRecords
{
    protected static string $resource = SeminarResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add Seminar')];
    }
}
