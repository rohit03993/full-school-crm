<?php

namespace App\Support;

class AttendanceSourceLabel
{
    public static function for(?string $source): string
    {
        return match ($source) {
            'biometric' => 'Biometric',
            'manual' => 'Manual IN/OUT',
            'roll_call' => 'Roll call (A/L)',
            'punch' => 'Biometric',
            default => '—',
        };
    }

    public static function visitState(?\Illuminate\Support\Carbon $checkedIn, ?\Illuminate\Support\Carbon $checkedOut): ?string
    {
        if (! $checkedIn) {
            return null;
        }

        if (! $checkedOut) {
            return 'Inside';
        }

        return 'Checked out';
    }
}
