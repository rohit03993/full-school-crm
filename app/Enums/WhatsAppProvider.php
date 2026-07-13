<?php

namespace App\Enums;

enum WhatsAppProvider: string
{
    case Meta = 'meta';

    public function label(): string
    {
        return match ($this) {
            self::Meta => 'Meta WhatsApp',
        };
    }
}
