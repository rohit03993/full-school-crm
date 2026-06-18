<?php

namespace App\Enums;

enum BatchShift: string
{
    case Morning = 'morning';
    case Afternoon = 'afternoon';
    case Evening = 'evening';
    case Flexible = 'flexible';

    public function label(): string
    {
        return match ($this) {
            self::Morning => 'Morning',
            self::Afternoon => 'Afternoon',
            self::Evening => 'Evening',
            self::Flexible => 'Flexible',
        };
    }
}
