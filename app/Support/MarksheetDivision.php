<?php

namespace App\Support;

class MarksheetDivision
{
    public static function fromPercentage(?float $percentage): ?string
    {
        if ($percentage === null) {
            return null;
        }

        return match (true) {
            $percentage >= 55 => 'First Division',
            $percentage >= 48 => 'Second Division',
            $percentage >= 40 => 'Pass',
            default => 'Fail',
        };
    }
}
