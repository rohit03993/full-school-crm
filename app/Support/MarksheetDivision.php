<?php

namespace App\Support;

class MarksheetDivision
{
    public static function fromPercentage(?float $percentage): ?string
    {
        if ($percentage === null) {
            return null;
        }

        $thresholds = InstituteSettings::marksheetDivisionThresholds();

        return match (true) {
            $percentage >= $thresholds['first'] => 'First Division',
            $percentage >= $thresholds['second'] => 'Second Division',
            $percentage >= $thresholds['pass'] => 'Pass',
            default => 'Fail',
        };
    }
}
