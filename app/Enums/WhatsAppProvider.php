<?php

namespace App\Enums;

enum WhatsAppProvider: string
{
    case Meta = 'meta';
    case PalDigital = 'pal_digital';

    public function label(): string
    {
        return match ($this) {
            self::Meta => 'Meta WhatsApp',
            self::PalDigital => 'Pal Digital',
        };
    }
}
