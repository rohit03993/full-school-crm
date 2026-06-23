<?php

namespace App\Filament\Resources\AcademicSessions\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\AcademicSessions\AcademicSessionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAcademicSession extends EditRecord
{
    use ShowsCrmPageHint;

    protected static string $resource = AcademicSessionResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'sessions.edit';
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
