<?php

namespace App\Enums;

enum FeePenaltyType: string
{
    case LateFee = 'late_fee';

    public function label(): string
    {
        return match ($this) {
            self::LateFee => 'Late fee',
        };
    }
}
