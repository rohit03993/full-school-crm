<?php

namespace App\Support;

class IndianMobileNumber
{
    /**
     * Normalize pasted or typed mobile input to a 10-digit Indian number, or null if invalid.
     */
    public static function normalize(?string $input): ?string
    {
        if (blank($input)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', (string) $input);

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : null;
    }
}
