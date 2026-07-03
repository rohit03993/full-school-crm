<?php

namespace App\Services\Punch;

use App\Models\AttendanceManualPunch;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PunchAttendanceProcessor
{
    public function __construct(
        protected PunchLogService $logs,
        protected PunchInOutCalculator $calculator,
        protected PunchAttendanceSyncService $sync,
        protected PunchWhatsAppService $whatsapp,
    ) {}

    /**
     * @return array{processed: int, synced: int, notified: int}
     */
    public function processPending(): array
    {
        $stats = ['processed' => 0, 'synced' => 0, 'notified' => 0];

        if ($this->logs->notificationQueueExists()) {
            foreach ($this->logs->unprocessedNotificationQueue() as $item) {
                $handled = $this->handleQueueItem($item);
                $stats['processed']++;
                $stats['synced'] += $handled['synced'] ? 1 : 0;
                $stats['notified'] += $handled['whatsapp']['queued'] ? 1 : 0;
            }
        }

        if ($this->logs->punchTableExists()) {
            $lastId = (int) Setting::getValue('attendance.last_processed_punch_log_id', 0);
            $limit = max(1, (int) config('attendance.process_batch_size', 100));

            foreach ($this->logs->newMachinePunchesSince($lastId, $limit) as $punch) {
                $handled = $this->handleMachinePunch($punch);
                $stats['processed']++;
                $stats['synced'] += $handled['synced'] ? 1 : 0;
                $stats['notified'] += $handled['whatsapp']['queued'] ? 1 : 0;

                Setting::setValue('attendance.last_processed_punch_log_id', (string) $punch->id, 'attendance');
            }
        }

        return $stats;
    }

    /**
     * @return array{synced: bool, whatsapp: array{queued: bool, message: string}}
     */
    public function handleManualPunch(
        Student $student,
        string $enrollmentNumber,
        string $date,
        string $time,
        string $state,
        User $staff,
    ): array {
        AttendanceManualPunch::query()->create([
            'enrollment_number' => $this->logs->normalizeRoll($enrollmentNumber),
            'punch_date' => $date,
            'punch_time' => $time,
            'state' => $state,
            'marked_by_user_id' => $staff->id,
        ]);

        if ($this->logs->punchTableExists()) {
            $payload = [
                'employee_id' => $this->logs->normalizeRoll($enrollmentNumber),
                'punch_date' => $date,
                'punch_time' => $time,
                'device_name' => 'Manual',
                'verify_type_char' => 'M',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if ($this->columnExists($this->logs->punchTable(), 'is_manual')) {
                $payload['is_manual'] = 1;
            }

            DB::table($this->logs->punchTable())->insert($payload);
        }

        return $this->applyPunchEffects($student, $enrollmentNumber, $date, $time, $state, $staff);
    }

    /**
     * @return array{synced: bool, notified: bool}
     */
    private function handleMachinePunch(object $punch): array
    {
        $roll = $this->logs->normalizeRoll((string) ($punch->employee_id ?? ''));
        $date = (string) $punch->punch_date;
        $time = (string) $punch->punch_time;

        $student = $this->logs->findStudentByRoll($roll);

        if (! $student) {
            return ['synced' => false, 'notified' => false];
        }

        $state = $this->resolveState($roll, $date, $time);

        return $this->applyPunchEffects($student, $roll, $date, $time, $state);
    }

    /**
     * @return array{synced: bool, notified: bool}
     */
    private function handleQueueItem(object $item): array
    {
        $roll = $this->logs->normalizeRoll((string) ($item->roll_number ?? ''));
        $date = (string) $item->punch_date;
        $time = (string) $item->punch_time;

        try {
            $student = $this->logs->findStudentByRoll($roll);

            if (! $student) {
                return ['synced' => false, 'notified' => false];
            }

            $state = $this->resolveState($roll, $date, $time);

            return $this->applyPunchEffects($student, $roll, $date, $time, $state);
        } finally {
            DB::table('notification_queue')
                ->where('id', $item->id)
                ->update(['processed' => true, 'processed_at' => now()]);
        }
    }

    /**
     * @return array{synced: bool, whatsapp: array{queued: bool, message: string}}
     */
    private function applyPunchEffects(
        Student $student,
        string $roll,
        string $date,
        string $time,
        string $state,
        ?User $staff = null,
    ): array {
        $synced = false;

        if ($state === 'IN') {
            $this->sync->syncFromPunch($student, $date, $state, $time, $staff ? 'manual' : 'biometric', $staff?->id);
            $synced = true;
        } elseif ($state === 'OUT') {
            $this->sync->syncFromPunch($student, $date, $state, $time, $staff ? 'manual' : 'biometric', $staff?->id);
            $synced = true;
        }

        $whatsapp = $this->whatsapp->outcomeForPunch($student, $roll, $date, $time, $state, $staff);

        return ['synced' => $synced, 'whatsapp' => $whatsapp];
    }

    private function resolveState(string $roll, string $date, string $time): string
    {
        $punches = $this->logs->unifiedPunchesForRollDate($roll, $date);

        return $this->calculator->stateForPunch($punches, $time, $date);
    }

    private function columnExists(string $table, string $column): bool
    {
        return DB::getSchemaBuilder()->hasColumn($table, $column);
    }
}
