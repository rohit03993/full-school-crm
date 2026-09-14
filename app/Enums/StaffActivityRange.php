<?php

namespace App\Enums;

use Illuminate\Support\Carbon;

enum StaffActivityRange: string
{
    case Today = 'today';
    case Week = 'week';
    case Month = 'month';
    case Year = 'year';

    public function label(): string
    {
        return match ($this) {
            self::Today => 'Today',
            self::Week => 'This week',
            self::Month => 'This month',
            self::Year => 'This year',
        };
    }

    public static function fromRequest(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Today;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function bounds(): array
    {
        $end = now()->endOfDay();

        $start = match ($this) {
            self::Week => now()->startOfWeek(),
            self::Month => now()->startOfMonth(),
            self::Year => now()->startOfYear(),
            self::Today => now()->startOfDay(),
        };

        return [$start, $end];
    }
}
