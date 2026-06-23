<?php

namespace App\Filament\Resources\AcademicSessions\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\AcademicSessions\AcademicSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAcademicSessions extends ListRecords
{
    use ShowsCrmPageHint;

    protected static function crmHintKey(): ?string
    {
        return 'sessions.list';
    }

    protected static string $resource = AcademicSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
