<?php

namespace App\Filament\Resources\AcademicSessions\Pages;

use App\Filament\Resources\AcademicSessions\AcademicSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAcademicSessions extends ListRecords
{
    protected static string $resource = AcademicSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
