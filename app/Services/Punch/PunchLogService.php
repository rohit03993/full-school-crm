<?php

namespace App\Services\Punch;

use App\Models\AttendanceManualPunch;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PunchLogService
{
    public function punchTable(): string
    {
        return (string) config('attendance.punch_table', 'punch_logs');
    }

    public function punchTableExists(): bool
    {
        return Schema::hasTable($this->punchTable());
    }

    public function notificationQueueExists(): bool
    {
        return Schema::hasTable('notification_queue');
    }

    public function normalizeRoll(?string $roll): string
    {
        return strtoupper(trim((string) $roll));
    }

    public function findStudentByRoll(string $roll): ?Student
    {
        $roll = $this->normalizeRoll($roll);

        if ($roll === '') {
            return null;
        }

        $enrollment = Enrollment::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(enrollment_number) = ?', [$roll])
            ->with(['student.activeBatchStudent.batch', 'student.activeEnrollment'])
            ->first();

        return $enrollment?->student;
    }

    /**
     * @return Collection<int, object>
     */
    public function machinePunchesForRollDate(string $roll, string $date): Collection
    {
        if (! $this->punchTableExists()) {
            return collect();
        }

        $table = $this->punchTable();
        $select = 'employee_id, punch_date, punch_time, 0 as is_manual';

        if (Schema::hasColumn($table, 'device_name')) {
            $select .= ', device_name';
        }

        if (Schema::hasColumn($table, 'area_name')) {
            $select .= ', area_name';
        }

        return DB::table($table)
            ->selectRaw($select)
            ->where('employee_id', $this->normalizeRoll($roll))
            ->where('punch_date', $date)
            ->orderBy('punch_time')
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function manualPunchesForRollDate(string $roll, string $date): Collection
    {
        if (! Schema::hasTable('attendance_manual_punches')) {
            return collect();
        }

        return AttendanceManualPunch::query()
            ->selectRaw('enrollment_number as employee_id, punch_date, punch_time, 1 as is_manual, state')
            ->where('enrollment_number', $this->normalizeRoll($roll))
            ->whereDate('punch_date', $date)
            ->orderBy('punch_time')
            ->get()
            ->map(fn (AttendanceManualPunch $row): object => (object) [
                'employee_id' => $row->enrollment_number,
                'punch_date' => $row->punch_date->toDateString(),
                'punch_time' => strlen((string) $row->punch_time) > 5
                    ? substr((string) $row->punch_time, 0, 8)
                    : (string) $row->punch_time,
                'is_manual' => 1,
                'state' => $row->state,
            ]);
    }

    /**
     * @return Collection<int, object>
     */
    public function unifiedPunchesForRollDate(string $roll, string $date): Collection
    {
        return $this->machinePunchesForRollDate($roll, $date)
            ->concat($this->manualPunchesForRollDate($roll, $date))
            ->sortBy([
                ['punch_date', 'asc'],
                ['punch_time', 'asc'],
            ])
            ->values();
    }

    /**
     * @return Collection<int, object>
     */
    public function newMachinePunchesSince(int $lastId, int $limit = 100): Collection
    {
        if (! $this->punchTableExists()) {
            return collect();
        }

        return DB::table($this->punchTable())
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, object>
     */
    public function unprocessedNotificationQueue(int $limit = 50): Collection
    {
        if (! $this->notificationQueueExists()) {
            return collect();
        }

        return DB::table('notification_queue')
            ->where('processed', false)
            ->orderBy('queued_at')
            ->limit($limit)
            ->get();
    }
}
