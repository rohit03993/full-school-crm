<?php

namespace App\Services\Punch;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use Illuminate\Support\Carbon;

class AttendanceAutoOutService
{
    /**
     * Persist auto check-out on attendance rows that are still open past the cutoff.
     *
     * @return int Number of rows updated
     */
    public function applyDue(): int
    {
        if (! filter_var(config('attendance.auto_out_enabled', true), FILTER_VALIDATE_BOOL)) {
            return 0;
        }

        $autoOutTime = $this->normalizedAutoOutTime();
        $updated = 0;

        Attendance::query()
            ->where('status', AttendanceStatus::Present)
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($autoOutTime, &$updated): void {
                foreach ($rows as $row) {
                    $date = $row->attendance_date?->toDateString();

                    if (! is_string($date) || $date === '' || ! $this->shouldAutoOut($date, $autoOutTime)) {
                        continue;
                    }

                    $outAt = Carbon::parse($date.' '.$autoOutTime);

                    if ($row->checked_in_at && $outAt->lte($row->checked_in_at)) {
                        $outAt = $row->checked_in_at->copy()->addMinute();
                    }

                    $row->update([
                        'checked_out_at' => $outAt,
                    ]);

                    $updated++;
                }
            });

        return $updated;
    }

    public function shouldAutoOut(string $date, ?string $autoOutTime = null): bool
    {
        if (! filter_var(config('attendance.auto_out_enabled', true), FILTER_VALIDATE_BOOL)) {
            return false;
        }

        $autoOutTime ??= $this->normalizedAutoOutTime();
        $today = Carbon::today()->toDateString();
        $day = Carbon::parse($date)->toDateString();

        if ($day < $today) {
            return true;
        }

        if ($day > $today) {
            return false;
        }

        return now()->format('H:i') >= substr($autoOutTime, 0, 5);
    }

    public function normalizedAutoOutTime(): string
    {
        $raw = trim((string) config('attendance.auto_out_time', '20:00'));

        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $raw) !== 1) {
            return '20:00:00';
        }

        return strlen($raw) === 5 ? $raw.':00' : $raw;
    }
}
