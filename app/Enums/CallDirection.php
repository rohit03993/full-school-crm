<?php

namespace App\Enums;

enum CallDirection: string
{
    case Outgoing = 'outgoing';
    case Incoming = 'incoming';

    public function label(): string
    {
        return match ($this) {
            self::Outgoing => 'Outgoing',
            self::Incoming => 'Incoming',
        };
    }
}
