<?php

namespace App\Enums;

enum PaymentMode: string
{
    case Cash = 'cash';
    case Online = 'online';
    case Upi = 'upi';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::Online => 'Online',
            self::Upi => 'UPI',
        };
    }
}
