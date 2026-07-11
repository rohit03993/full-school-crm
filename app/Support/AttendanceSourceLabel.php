<?php

namespace App\Support;

use App\Models\Attendance;
use App\Models\Student;
use App\Services\Punch\LivePunchDashboardService;

class AttendanceSourceLabel
{
    public static function for(?string $source, ?string $staffName = null): string
    {
        return match ($source) {
            'biometric', 'punch' => 'Biometric',
            'manual' => self::manualMarked($staffName),
            'roll_call' => filled(trim((string) $staffName))
                ? 'Roll call ('.trim((string) $staffName).')'
                : 'Roll call (A/L)',
            default => '—',
        };
    }

    public static function manualMarked(?string $staffName = null): string
    {
        $name = trim((string) $staffName);

        return $name !== ''
            ? "Manually marked ({$name})"
            : 'Manually marked';
    }

    public static function isManual(?string $source): bool
    {
        return in_array($source, ['manual', 'roll_call'], true);
    }

    public static function forRecord(Attendance $record, ?Student $student = null): string
    {
        $record->loadMissing('markedBy');
        $roll = $student?->activeEnrollment?->enrollment_number;
        $fallbackStaff = $record->markedBy?->name;

        if (! filled($roll)) {
            return self::for($record->punch_source, $fallbackStaff);
        }

        $dayRow = app(LivePunchDashboardService::class)->studentDayRow(
            (string) $roll,
            $record->attendance_date->toDateString(),
            $student,
        );

        $pairs = $dayRow['pairs'] ?? [];

        if ($pairs === []) {
            return self::for($record->punch_source, $fallbackStaff);
        }

        $pair = $pairs[array_key_last($pairs)];
        $inLabel = self::channelLabel(
            (bool) ($pair['is_manual_in'] ?? false),
            $pair['device_in'] ?? null,
            $pair['marked_by_in'] ?? $fallbackStaff,
        );

        if (! filled($pair['out'] ?? null)) {
            return $inLabel;
        }

        if ($pair['is_auto_out'] ?? false) {
            return "{$inLabel} · Auto OUT";
        }

        $outLabel = self::channelLabel(
            (bool) ($pair['is_manual_out'] ?? false),
            $pair['device_out'] ?? null,
            $pair['marked_by_out'] ?? $fallbackStaff,
        );

        if ($inLabel === $outLabel) {
            return $inLabel;
        }

        return "{$inLabel} · {$outLabel}";
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

    private static function channelLabel(bool $isManual, mixed $device, ?string $staffName): string
    {
        if ($isManual) {
            return self::manualMarked($staffName);
        }

        $deviceLabel = trim((string) ($device ?? ''));

        if ($deviceLabel !== '' && strcasecmp($deviceLabel, 'Manual') !== 0 && strcasecmp($deviceLabel, 'Auto') !== 0) {
            return $deviceLabel;
        }

        return 'Biometric';
    }
}
