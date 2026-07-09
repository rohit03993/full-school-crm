<?php

namespace App\Enums;

enum FeeMiscChargeAdjustmentType: string
{
    case WaiveOff = 'waive_off';
    case Discount = 'discount';

    public function label(): string
    {
        return match ($this) {
            self::WaiveOff => 'Waive off',
            self::Discount => 'Discount',
        };
    }
}
