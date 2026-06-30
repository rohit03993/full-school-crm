<?php

namespace App\Services\Punch;

use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AttendanceBiometricStatusService
{
    public function __construct(
        protected PunchLogService $logs,
    ) {}

    /**
     * @return array{
     *     punch_table: string,
     *     punch_table_ready: bool,
     *     notification_queue_ready: bool,
     *     last_processed_punch_id: int,
     *     punch_row_count: int|null,
     *     processor_command: string,
     * }
     */
    public function status(): array
    {
        $table = $this->logs->punchTable();
        $ready = $this->logs->punchTableExists();

        return [
            'punch_table' => $table,
            'punch_table_ready' => $ready,
            'notification_queue_ready' => $this->logs->notificationQueueExists(),
            'last_processed_punch_id' => (int) Setting::getValue('attendance.last_processed_punch_log_id', 0),
            'punch_row_count' => $ready ? (int) DB::table($table)->count() : null,
            'processor_command' => 'php artisan attendance:process-punches --continuous --interval=30',
        ];
    }

    public function rollMappingHint(): string
    {
        return 'EasyTimePro employee ID on each punch must match the student enrollment number (roll) in this CRM — e.g. CRM-2026-000042 or your custom roll.';
    }
}
