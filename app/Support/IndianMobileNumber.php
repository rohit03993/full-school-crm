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

        $input = trim((string) $input);

        if (preg_match('/^[\d.]+[eE][+\-]?\d+$/', $input)) {
            $input = sprintf('%.0f', (float) $input);
        }

        $digits = preg_replace('/\D/', '', $input) ?? '';

        return self::digitsToTenDigitMobile($digits);
    }

    /**
     * Normalize a spreadsheet cell value (string, int, or float from Excel) to 10 digits.
     */
    public static function normalizeFromSpreadsheet(mixed $input): ?string
    {
        if ($input === null || $input === '') {
            return null;
        }

        if (is_bool($input)) {
            return null;
        }

        if (is_int($input)) {
            return self::normalize((string) $input);
        }

        if (is_float($input)) {
            if (abs($input) >= 1_000_000_000) {
                return self::normalize(sprintf('%.0f', $input));
            }

            return self::normalize(self::stringifyFloat($input));
        }

        return self::normalize((string) $input);
    }

    /**
     * @return list<string>
     */
    public static function acceptedFormatHints(): array
    {
        return [
            '10-digit mobile: 8410054825',
            'With country code: 919027620525 or +91 9027620525',
            'Excel number / scientific notation is accepted — stored as 10 digits in CRM',
        ];
    }

    protected static function digitsToTenDigitMobile(string $digits): ?string
    {
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) > 10) {
            $digits = substr($digits, -10);
        }

        return preg_match('/^[6-9]\d{9}$/', $digits) ? $digits : null;
    }

    protected static function stringifyFloat(float $value): string
    {
        if (abs($value) >= 1_000_000_000 || abs($value) < 0.0001) {
            return sprintf('%.0f', $value);
        }

        return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }
}
