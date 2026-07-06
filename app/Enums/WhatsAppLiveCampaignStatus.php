<?php

namespace App\Enums;

enum WhatsAppLiveCampaignStatus: string
{
    case Draft = 'draft';
    case Live = 'live';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Live => 'Live',
        };
    }
}
