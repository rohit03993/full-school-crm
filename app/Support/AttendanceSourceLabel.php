<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Student;
use App\Services\Punch\LivePunchDashboardService;

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

    public static function forRecord(Attendance $record, ?Student $student = null): string
    {
        $roll = $student?->activeEnrollment?->enrollment_number;

        if (! filled($roll)) {
            return self::for($record->punch_source);
        }

        $dayRow = app(LivePunchDashboardService::class)->studentDayRow(
            (string) $roll,
            $record->attendance_date->toDateString(),
            $student,
        );

        $pairs = $dayRow['pairs'] ?? [];

        if ($pairs === []) {
            return self::for($record->punch_source);
        }

        $pair = $pairs[array_key_last($pairs)];
        $inLabel = ($pair['is_manual_in'] ?? false)
            ? 'Manual'
            : (filled($pair['device_in'] ?? null) ? (string) $pair['device_in'] : 'Biometric');

        if (! filled($pair['out'] ?? null)) {
            return "{$inLabel} IN";
        }

        if ($pair['is_auto_out'] ?? false) {
            return "{$inLabel} IN · Auto OUT";
        }

        $outLabel = ($pair['is_manual_out'] ?? false)
            ? 'Manual'
            : (filled($pair['device_out'] ?? null) ? (string) $pair['device_out'] : 'Biometric');

        if ($inLabel !== $outLabel) {
            return "{$inLabel} IN · {$outLabel} OUT";
        }

        return "{$outLabel} IN/OUT";
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
