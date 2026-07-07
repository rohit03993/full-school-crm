<?php

namespace App\Enums;

enum FeeMiscChargeStatus: string
{
    case Bundled = 'bundled';
    case Pending = 'pending';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Bundled => 'In fee plan',
            self::Pending => 'Pending',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }
}
