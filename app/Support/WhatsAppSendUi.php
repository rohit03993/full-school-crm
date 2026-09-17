<?php

namespace App\Support;

use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;

class WhatsAppSendUi
{
    /**
     * Disable a Filament/Livewire send button while the click is being processed.
     *
     * @return array<string, string>
     */
    public static function loadingAttributes(): array
    {
        return [
            'wire:loading.attr' => 'disabled',
            'wire:loading.class' => 'opacity-70 cursor-wait',
        ];
    }

    public static function campaignViewUrl(int|string|null $campaignId): ?string
    {
        if (blank($campaignId) || ! WhatsAppCampaignResource::canAccess()) {
            return null;
        }

        return WhatsAppCampaignResource::getUrl('view', ['record' => $campaignId]);
    }
}
