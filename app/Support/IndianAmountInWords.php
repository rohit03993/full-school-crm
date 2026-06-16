<?php

namespace App\Support;

class IndianAmountInWords
{
    private const ONES = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
        'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];

    private const TENS = [
        '', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety',
    ];

    public static function format(float|int|string $amount): string
    {
        $value = round((float) $amount, 2);
        $rupees = (int) floor($value);
        $paise = (int) round(($value - $rupees) * 100);

        if ($rupees === 0 && $paise === 0) {
            return 'Zero Rupees Only';
        }

        $words = $rupees > 0
            ? self::numberToWords($rupees).' Rupee'.($rupees === 1 ? '' : 's')
            : '';

        if ($paise > 0) {
            $paiseWords = self::numberToWords($paise).' Paise';
            $words = $words !== '' ? "{$words} and {$paiseWords}" : $paiseWords;
        }

        return $words.' Only';
    }

    protected static function numberToWords(int $number): string
    {
        if ($number === 0) {
            return 'Zero';
        }

        $parts = [];

        if ($number >= 10000000) {
            $parts[] = self::numberToWords((int) floor($number / 10000000)).' Crore';
            $number %= 10000000;
        }

        if ($number >= 100000) {
            $parts[] = self::numberToWords((int) floor($number / 100000)).' Lakh';
            $number %= 100000;
        }

        if ($number >= 1000) {
            $parts[] = self::numberToWords((int) floor($number / 1000)).' Thousand';
            $number %= 1000;
        }

        if ($number >= 100) {
            $parts[] = self::numberToWords((int) floor($number / 100)).' Hundred';
            $number %= 100;
        }

        if ($number >= 20) {
            $parts[] = self::TENS[(int) floor($number / 10)];
            $number %= 10;
        }

        if ($number > 0) {
            $parts[] = self::ONES[$number];
        }

        return implode(' ', array_filter($parts));
    }
}
