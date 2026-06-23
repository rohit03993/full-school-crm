<?php

namespace App\Filament\Resources\WhatsAppTemplates\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\WhatsAppTemplates\WhatsAppTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppTemplates extends ListRecords
{
    use ShowsCrmPageHint;

    protected static string $resource = WhatsAppTemplateResource::class;

    protected static function crmHintKey(): ?string
    {
        return 'whatsapp.templates';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
