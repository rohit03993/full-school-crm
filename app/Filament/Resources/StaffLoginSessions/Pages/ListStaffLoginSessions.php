<?php

namespace App\Filament\Resources\StaffLoginSessions\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\StaffLoginSessions\StaffLoginSessionResource;
use Filament\Resources\Pages\ListRecords;

class ListStaffLoginSessions extends ListRecords
{
    use ShowsCrmPageHint;

    protected static string $resource = StaffLoginSessionResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'staff.login_log';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
