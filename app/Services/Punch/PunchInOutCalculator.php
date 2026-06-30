<?php

namespace App\Services\Punch;

use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class PunchInOutCalculator
{
    public function bounceWindowSeconds(): int
    {
        return max(1, (int) config('attendance.punch_bounce_seconds', 10));
    }

    public function gapMinutes(): int
    {
        return max(1, (int) config('attendance.punch_gap_minutes', 2));
    }

    /**
     * @param  Collection<int, object>  $punches  sorted asc by date/time
     * @return array{0: array<string, list<array<string, mixed>>>, 1: list<array<string, mixed>>}
     */
    public function computeInOut(Collection $punches): array
    {
        $daily = [];
        $raw = [];

        $byDate = $punches->groupBy(fn (object $p): string => (string) $p->punch_date);

        foreach ($byDate as $date => $list) {
            $sorted = $list->sort(function (object $a, object $b): int {
                $cmp = strcmp((string) $a->punch_time, (string) $b->punch_time);

                if ($cmp === 0) {
                    $manualA = (bool) ($a->is_manual ?? false);
                    $manualB = (bool) ($b->is_manual ?? false);

                    return $manualA === $manualB ? 0 : ($manualA ? -1 : 1);
                }

                return $cmp;
            })->values();

            $acceptedCount = 0;
            $lastAcceptedTime = null;
            $entries = [];
            $currentPairIndex = -1;

            foreach ($sorted as $p) {
                $fullTime = $this->normalizeTime((string) $p->punch_time);
                $current = Carbon::parse($date.' '.$fullTime);

                if ($lastAcceptedTime === null) {
                    $acceptedCount = 1;
                    $entries[] = $this->openPair($p);
                    $currentPairIndex = count($entries) - 1;
                    $lastAcceptedTime = $current;

                    continue;
                }

                $secondsDiff = abs($current->diffInSeconds($lastAcceptedTime));
                $minutesDiff = abs($current->diffInMinutes($lastAcceptedTime));

                if ($secondsDiff < $this->bounceWindowSeconds()) {
                    $raw[] = ['date' => $date, 'time' => $p->punch_time, 'note' => 'duplicate-skipped'];

                    continue;
                }

                if ($minutesDiff < $this->gapMinutes()) {
                    $raw[] = ['date' => $date, 'time' => $p->punch_time, 'note' => 'ignored-gap-too-small'];

                    continue;
                }

                $acceptedCount++;
                $state = ($acceptedCount % 2 === 1) ? 'IN' : 'OUT';

                if ($state === 'IN') {
                    $entries[] = $this->openPair($p);
                    $currentPairIndex = count($entries) - 1;
                } elseif ($currentPairIndex >= 0 && isset($entries[$currentPairIndex])) {
                    $entries[$currentPairIndex]['out'] = (string) $p->punch_time;
                    $entries[$currentPairIndex]['is_manual_out'] = (bool) ($p->is_manual ?? false);
                }

                $lastAcceptedTime = $current;
            }

            $entries = $this->applyAutoOut($date, $entries);
            $daily[$date] = $entries;
        }

        return [$daily, $raw];
    }

    /**
     * @param  Collection<int, object>  $punches
     */
    public function stateForPunch(Collection $punches, string $targetTime, string $punchDate): string
    {
        $acceptedCount = 0;
        $lastAcceptedTime = null;

        foreach ($punches as $p) {
            $fullTime = $this->normalizeTime((string) $p->punch_time);
            $current = Carbon::parse($punchDate.' '.$fullTime);
            $isTarget = ((string) $p->punch_time === $targetTime)
                || ($this->normalizeTime((string) $p->punch_time) === $this->normalizeTime($targetTime));

            if ($lastAcceptedTime === null) {
                $acceptedCount = 1;

                if ($isTarget) {
                    return 'IN';
                }

                $lastAcceptedTime = $current;

                continue;
            }

            $secondsDiff = abs($current->diffInSeconds($lastAcceptedTime));
            $minutesDiff = abs($current->diffInMinutes($lastAcceptedTime));

            if ($secondsDiff < $this->bounceWindowSeconds() || $minutesDiff < $this->gapMinutes()) {
                if ($isTarget) {
                    return ($acceptedCount % 2 === 1) ? 'IN' : 'OUT';
                }

                continue;
            }

            $acceptedCount++;
            $state = ($acceptedCount % 2 === 1) ? 'IN' : 'OUT';

            if ($isTarget) {
                return $state;
            }

            $lastAcceptedTime = $current;
        }

        return 'IN';
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<array<string, mixed>>
     */
    private function applyAutoOut(string $date, array $entries): array
    {
        if (! filter_var(config('attendance.auto_out_enabled', true), FILTER_VALIDATE_BOOL)) {
            return $entries;
        }

        $autoOutTime = (string) config('attendance.auto_out_time', '19:00');
        $today = Carbon::today()->toDateString();
        $currentDate = Carbon::parse($date);
        $isPastDate = $currentDate->toDateString() < $today;
        $isToday = $currentDate->toDateString() === $today;
        $autoOutHour = (int) substr($autoOutTime, 0, 2);
        $autoOutMinute = (int) substr($autoOutTime, 3, 2);
        $isPastAutoOutTime = now()->hour > $autoOutHour
            || (now()->hour === $autoOutHour && now()->minute >= $autoOutMinute);

        foreach ($entries as &$entry) {
            if ($entry['in'] && ! $entry['out'] && ($isPastDate || ($isToday && $isPastAutoOutTime))) {
                $entry['out'] = $autoOutTime.':00';
                $entry['is_auto_out'] = true;
            }
        }

        return $entries;
    }

    /**
     * @return array<string, mixed>
     */
    private function openPair(object $p): array
    {
        return [
            'in' => (string) $p->punch_time,
            'out' => null,
            'is_manual_in' => (bool) ($p->is_manual ?? false),
            'is_manual_out' => null,
            'is_auto_out' => false,
        ];
    }

    private function normalizeTime(string $time): string
    {
        return strlen($time) === 5 ? $time.':00' : $time;
    }
}
