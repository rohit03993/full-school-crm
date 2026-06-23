<?php

namespace App\Enums;

enum WhatsAppAutoStatus: string
{
    case Queued = 'queued';
    case Success = 'success';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'WhatsApp queued',
            self::Success => 'WhatsApp sent',
            self::Failed => 'WhatsApp failed',
            self::Skipped => 'WhatsApp skipped',
        };
    }
}
