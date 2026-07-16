<?php

namespace App\Services\Punch;

use App\Models\BiometricDevice;
use App\Models\BiometricPunch;
use App\Models\FaceVerificationRequest;
use App\Models\Setting;
use App\Services\FaceVerify\FaceVerifyClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class AttendanceBiometricStatusService
{
    public function __construct(
        protected PunchLogService $logs,
        protected FaceVerifyClient $faceVerifyClient,
    ) {}

    /**
     * @return array{
     *     punch_table: string,
     *     punch_table_ready: bool,
     *     notification_queue_ready: bool,
     *     last_processed_punch_id: int,
     *     punch_row_count: int|null,
     *     processor_command: string,
     *     adms_enabled: bool,
     *     adms_url: string,
     *     device_count: int,
     *     active_device_count: int,
     *     raw_punch_count: int,
     *     devices: list<array{name: string, serial: string, last_seen_at: ?string, last_punch_at: ?string, today: int, is_active: bool, requires_face_verify: bool}>,
     *     face_verify_enabled: bool,
     *     face_verify_configured: bool,
     *     face_verify_api_url: ?string,
     *     face_verify_health: string,
     *     face_verify_pending_count: int,
     *     face_verify_callback_url: string,
     * }
     */
    public function status(): array
    {
        $table = $this->logs->punchTable();
        $ready = $this->logs->punchTableExists();
        $devicesReady = Schema::hasTable('biometric_devices');

        $devices = [];

        if ($devicesReady) {
            $devices = BiometricDevice::query()
                ->orderBy('name')
                ->get()
                ->map(fn (BiometricDevice $device): array => [
                    'name' => $device->name,
                    'serial' => $device->serial_number,
                    'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                    'last_punch_at' => $device->last_punch_at?->toIso8601String(),
                    'today' => $device->today_punch_count_date?->isToday()
                        ? (int) $device->today_punch_count
                        : 0,
                    'is_active' => $device->is_active,
                    'requires_face_verify' => (bool) $device->requires_face_verify,
                ])
                ->all();
        }

        return [
            'punch_table' => $table,
            'punch_table_ready' => $ready,
            'notification_queue_ready' => $this->logs->notificationQueueExists(),
            'last_processed_punch_id' => (int) Setting::getValue('attendance.last_processed_punch_log_id', 0),
            'punch_row_count' => $ready ? (int) DB::table($table)->count() : null,
            'processor_command' => 'php artisan attendance:process-punches',
            'adms_enabled' => (bool) config('biometric.enabled', true),
            'adms_url' => url('/'.trim((string) config('biometric.route_prefix', 'iclock'), '/')),
            'device_count' => $devicesReady ? BiometricDevice::query()->count() : 0,
            'active_device_count' => $devicesReady ? BiometricDevice::query()->where('is_active', true)->count() : 0,
            'raw_punch_count' => Schema::hasTable('biometric_punches') ? BiometricPunch::query()->count() : 0,
            'devices' => $devices,
            'face_verify_enabled' => (bool) config('face_verify.enabled', false),
            'face_verify_configured' => $this->faceVerifyClient->isConfigured(),
            'face_verify_api_url' => config('face_verify.api_url'),
            'face_verify_health' => $this->faceVerifyHealthLabel(),
            'face_verify_pending_count' => Schema::hasTable('face_verification_requests')
                ? FaceVerificationRequest::query()->where('status', FaceVerificationRequest::STATUS_PENDING)->count()
                : 0,
            'face_verify_callback_url' => url('/api/face-verify/approve'),
        ];
    }

    public function rollMappingHint(): string
    {
        return 'Device user PIN / employee ID on each punch must match the student enrollment number (roll) in this CRM.';
    }

    protected function faceVerifyHealthLabel(): string
    {
        if (! config('face_verify.enabled', false)) {
            return 'Disabled';
        }

        if (! $this->faceVerifyClient->isConfigured()) {
            return 'Not configured';
        }

        try {
            $this->faceVerifyClient->health();

            return 'Healthy';
        } catch (Throwable) {
            return 'Unreachable';
        }
    }
}
