<?php

namespace App\Enums;

enum StaffLoginMethod: string
{
    case Otp = 'otp';
    case Password = 'password';

    public function label(): string
    {
        return match ($this) {
            self::Otp => 'WhatsApp OTP',
            self::Password => 'Password',
        };
    }
}
