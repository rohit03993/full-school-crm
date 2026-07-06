<?php

namespace App\Filament\Resources\WhatsAppLiveCampaigns\Pages;

use App\Filament\Concerns\ShowsCrmPageHint;
use App\Filament\Resources\WhatsAppLiveCampaigns\WhatsAppLiveCampaignResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateWhatsAppLiveCampaign extends CreateRecord
{
    use ShowsCrmPageHint;

    protected static string $resource = WhatsAppLiveCampaignResource::class;

    protected static ?string $title = 'New live API campaign';

    protected static function crmHintKey(): ?string
    {
        return 'whatsapp.live_campaigns.create';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();

        return $data;
    }
}
