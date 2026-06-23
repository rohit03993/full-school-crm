<?php

namespace App\Enums;

enum WhatsAppCampaignStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Running = 'running';
    case Paused = 'paused';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Queued => 'Queued',
            self::Running => 'Running',
            self::Paused => 'Paused',
            self::Completed => 'Completed',
        };
    }
}
