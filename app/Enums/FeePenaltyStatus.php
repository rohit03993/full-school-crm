<?php

namespace App\Enums;

enum FeePenaltyStatus: string
{
    case Pending = 'pending';
    case Waived = 'waived';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Waived => 'Waived',
            self::Paid => 'Paid',
        };
    }
}
