<?php

namespace App\Enums;

enum StudentCategory: string
{
    case General = 'general';
    case Obc = 'obc';
    case Sc = 'sc';
    case St = 'st';
    case Ews = 'ews';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Obc => 'OBC',
            self::Sc => 'SC',
            self::St => 'ST',
            self::Ews => 'EWS',
        };
    }
}
