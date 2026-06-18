<?php

namespace App\Support;

class FeePaymentPolicy
{
    public static function usesFlexibleAllocation(): bool
    {
        return config('fees.payment.allocation') !== 'strict';
    }

    public static function allocationLabel(): string
    {
        return self::usesFlexibleAllocation() ? 'flexible' : 'strict';
    }
}
