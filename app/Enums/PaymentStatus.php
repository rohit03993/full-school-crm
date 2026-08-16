<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Active = 'active';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Cancelled => 'Cancelled',
        };
    }
}
