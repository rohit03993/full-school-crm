<?php

namespace App\Enums;

enum DurationType: string
{
    case Months = 'months';
    case Years = 'years';

    public function label(): string
    {
        return match ($this) {
            self::Months => 'Months',
            self::Years => 'Years',
        };
    }
}
