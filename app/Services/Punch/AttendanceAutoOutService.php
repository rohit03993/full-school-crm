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
     * Rules:
     * - Checked in before cutoff → check out at cutoff (e.g. 20:00) once that time has passed
     * - Checked in after cutoff (evening) → check out after a grace period from check-in
     * - Past calendar days with no checkout → always closed
     *
     * @return int Number of rows updated
     */
    public function applyDue(): int
    {
        if (! filter_var(config('attendance.auto_out_enabled', true), FILTER_VALIDATE_BOOL)) {
            return 0;
        }

        $autoOutTime = $this->normalizedAutoOutTime();
        $graceMinutes = max(0, (int) config('attendance.auto_out_late_grace_minutes', 60));
        $updated = 0;

        Attendance::query()
            ->where('status', AttendanceStatus::Present)
            ->whereNotNull('checked_in_at')
            ->whereNull('checked_out_at')
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($autoOutTime, $graceMinutes, &$updated): void {
                foreach ($rows as $row) {
                    $date = $row->attendance_date?->toDateString();

                    if (! is_string($date) || $date === '' || ! $row->checked_in_at) {
                        continue;
                    }

                    $outAt = $this->resolveAutoOutAt($date, $row->checked_in_at, $autoOutTime, $graceMinutes);

                    if ($outAt === null || $outAt->isFuture()) {
                        continue;
                    }

                    $row->update([
                        'checked_out_at' => $outAt,
                    ]);

                    $updated++;
                }
            });

        return $updated;
    }

    public function resolveAutoOutAt(
        string $date,
        Carbon $checkedInAt,
        ?string $autoOutTime = null,
        ?int $graceMinutes = null,
    ): ?Carbon {
        if (! filter_var(config('attendance.auto_out_enabled', true), FILTER_VALIDATE_BOOL)) {
            return null;
        }

        $autoOutTime ??= $this->normalizedAutoOutTime();
        $graceMinutes ??= max(0, (int) config('attendance.auto_out_late_grace_minutes', 60));

        $today = Carbon::today()->toDateString();
        $day = Carbon::parse($date)->toDateString();

        if ($day > $today) {
            return null;
        }

        $cutoff = Carbon::parse($date.' '.$autoOutTime);

        // Normal day: IN before cutoff → OUT at cutoff (once day is past, or today after cutoff).
        if ($checkedInAt->lt($cutoff)) {
            if ($day < $today || now()->gte($cutoff)) {
                return $cutoff;
            }

            return null;
        }

        // Late / evening IN after cutoff → OUT after grace from check-in (or immediately on a past day).
        $lateOut = $checkedInAt->copy()->addMinutes($graceMinutes);

        if ($day < $today || now()->gte($lateOut)) {
            return $lateOut;
        }

        return null;
    }

    /** @deprecated Use resolveAutoOutAt(); kept for callers/tests. */
    public function shouldAutoOut(string $date, ?string $autoOutTime = null): bool
    {
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
