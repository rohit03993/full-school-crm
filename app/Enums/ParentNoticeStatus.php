<?php

namespace App\Enums;

enum ParentNoticeStatus: string
{
    case Waiting = 'waiting';
    case Sent = 'sent';
    case Declined = 'declined';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Waiting for approval',
            self::Sent => 'Parent message sent',
            self::Declined => 'Parent message not sent',
        };
    }
}
