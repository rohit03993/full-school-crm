<?php

namespace App\Filament\Resources\StandardCoursePlans\Pages;

use App\Filament\Resources\StandardCoursePlans\StandardCoursePlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListStandardCoursePlans extends ListRecords
{
    protected static string $resource = StandardCoursePlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'The recommended topics for one programme and one subject.';
    }
}
