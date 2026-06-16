<?php

namespace App\Filament\Resources\IndustrialVisits\Pages;

use App\Filament\Resources\IndustrialVisits\IndustrialVisitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIndustrialVisits extends ListRecords
{
    protected static string $resource = IndustrialVisitResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Add Visit')];
    }
}
