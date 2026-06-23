<?php

namespace App\Enums;

enum WhatsAppRecipientStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
        };
    }
}
