<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class PunchDuration
{
    public static function format(string $date, ?string $in, ?string $out): ?string
    {
        if (! filled($in) || ! filled($out)) {
            return null;
        }

        $start = Carbon::parse($date.' '.self::normalizeTime($in));
        $end = Carbon::parse($date.' '.self::normalizeTime($out));

        if ($end->lessThanOrEqualTo($start)) {
            return null;
        }

        $minutes = (int) $start->diffInMinutes($end);
        $hours = intdiv($minutes, 60);
        $remaining = $minutes % 60;

        if ($hours > 0) {
            return $hours.'h '.$remaining.'m';
        }

        return $remaining.'m';
    }

    private static function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
