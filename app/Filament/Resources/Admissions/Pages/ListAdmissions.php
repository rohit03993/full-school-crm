<?php

namespace App\Filament\Resources\Admissions\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\Admissions\AdmissionResource;
use Filament\Resources\Pages\ListRecords;

class ListAdmissions extends ListRecords
{
    use ShowsCrmPageHint;

    protected static function crmHintKey(): ?string
    {
        return 'admissions.list';
    }

    protected static string $resource = AdmissionResource::class;
}
