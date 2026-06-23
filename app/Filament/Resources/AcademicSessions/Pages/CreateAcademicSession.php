<?php

namespace App\Filament\Resources\AcademicSessions\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\AcademicSessions\AcademicSessionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAcademicSession extends CreateRecord
{
    use ShowsCrmPageHint;

    protected static string $resource = AcademicSessionResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'sessions.create';
    }
}
