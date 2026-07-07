<?php

namespace App\Enums;

enum FeeMiscChargeStatus: string
{
    case Bundled = 'bundled';
    case Pending = 'pending';
    case Partial = 'partial';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Bundled => 'In fee plan',
            self::Pending => 'Pending',
            self::Partial => 'Partially paid',
            self::Paid => 'Paid',
            self::Cancelled => 'Cancelled',
        };
    }
}
