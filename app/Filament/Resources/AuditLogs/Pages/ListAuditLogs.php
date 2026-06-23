<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    use ShowsCrmPageHint;

    protected static string $resource = AuditLogResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'audit.logs';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
