<?php

namespace App\Filament\Resources\WhatsAppCampaigns\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWhatsAppCampaigns extends ListRecords
{
    use ShowsCrmPageHint;

    protected static function crmHintKey(): ?string
    {
        return 'whatsapp.campaigns';
    }

    protected static string $resource = WhatsAppCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New campaign'),
        ];
    }
}
