<?php

namespace App\Enums;

enum AuthMethod: string
{
    case Dob = 'dob';
    case Otp = 'otp';
}
