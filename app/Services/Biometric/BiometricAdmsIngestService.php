<?php

namespace App\Services\Biometric;

use App\Models\BiometricDevice;
use App\Models\BiometricPunch;
use App\Services\FaceVerify\FaceVerifyGateService;
use App\Services\Punch\PunchAttendanceProcessor;
use App\Services\Punch\PunchLogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class BiometricAdmsIngestService
{
    public function __construct(
        protected PunchLogService $punchLogs,
        protected PunchAttendanceProcessor $processor,
        protected FaceVerifyGateService $faceVerify,
    ) {}

    public function findAllowedDevice(string $serial): ?BiometricDevice
    {
        $serial = strtoupper(trim($serial));

        if ($serial === '') {
            return null;
        }

        $device = BiometricDevice::query()
            ->whereRaw('UPPER(serial_number) = ?', [$serial])
            ->first();

        if (! $device) {
            return null;
        }

        if (config('biometric.require_allowlist', true) && ! $device->is_active) {
            return null;
        }

        return $device;
    }

    public function handshakeOptions(BiometricDevice $device): string
    {
        $sn = $device->serial_number;
        $attStamp = $device->attlog_stamp ?: 'None';
        $operStamp = $device->operlog_stamp ?: '9999';
        // K40 / ADMS expect UTC offset in minutes (IST = 330), not "Asia/Kolkata".
        $tzMinutes = $this->deviceTimezoneOffsetMinutes();

        $device->touchSeen();

        // CRLF + SyncTime: device applies TimeZone with the HTTP Date (GMT) header.
        return implode("\r\n", [
            "GET OPTION FROM: {$sn}",
            "ATTLOGStamp={$attStamp}",
            "OPERLOGStamp={$operStamp}",
            'ATTPHOTOStamp=None',
            'ErrorDelay=30',
            'Delay=10',
            'TransTimes=00:00;14:05',
            'TransInterval=1',
            'TransFlag=TransData AttLog OpLog',
            "TimeZone={$tzMinutes}",
            'SyncTime=60',
            'Realtime=1',
            'Encrypt=None',
        ])."\r\n";
    }

    /**
     * Force TimeZone onto the device via heartbeat — options=all often runs only once at boot.
     * Without this, a wrong first handshake leaves the clock stuck on UTC (IST − 5h30m).
     */
    public function pendingDeviceCommands(BiometricDevice $device): string
    {
        $interval = max(30, (int) config('biometric.time_sync_interval_seconds', 60));
        $cacheKey = 'biometric:adms:tz_sync:'.strtoupper($device->serial_number);
        $lastSyncedAt = (int) cache()->get($cacheKey, 0);

        if ($lastSyncedAt > 0 && (now()->getTimestamp() - $lastSyncedAt) < $interval) {
            $device->touchSeen();

            return 'OK';
        }

        cache()->put($cacheKey, now()->getTimestamp(), now()->addDay());
        $device->touchSeen();

        $tzMinutes = $this->deviceTimezoneOffsetMinutes();
        $cmdId = now()->getTimestamp();

        // C:CmdID:SET OPTION Key=Value — applied on next /iclock/getrequest poll.
        return "C:{$cmdId}:SET OPTION TimeZone={$tzMinutes}\r\n";
    }

    /**
     * ZKTeco ADMS TimeZone option: minutes east of UTC (e.g. 330 for India).
     */
    public function deviceTimezoneOffsetMinutes(): int
    {
        $configured = config('biometric.timezone_offset_minutes');

        if ($configured !== null && $configured !== '') {
            return (int) $configured;
        }

        $tz = config('biometric.timezone', config('app.timezone', 'Asia/Kolkata'));

        try {
            return (int) round(Carbon::now($tz)->getOffset() / 60);
        } catch (Throwable) {
            return 330;
        }
    }

    /**
     * @return array{accepted: int, mirrored: int, pending_face: int, duplicates: int, errors: int}
     */
    public function ingestAttLog(BiometricDevice $device, string $body, ?string $stamp = null): array
    {
        $stats = ['accepted' => 0, 'mirrored' => 0, 'pending_face' => 0, 'duplicates' => 0, 'errors' => 0];
        $lines = preg_split("/\r\n|\n|\r/", trim($body)) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '') {
                continue;
            }

            try {
                $parsed = $this->parseAttLogLine($line);

                if ($parsed === null) {
                    $stats['errors']++;
                    Log::warning('biometric.adms.attlog_parse_failed', [
                        'serial' => $device->serial_number,
                        'line' => $line,
                    ]);

                    continue;
                }

                $result = $this->storePunch($device, $parsed, $line, [
                    'stamp' => $stamp,
                    'table' => 'ATTLOG',
                ]);

                if ($result === 'duplicate') {
                    $stats['duplicates']++;
                } elseif ($result === 'mirrored') {
                    $stats['accepted']++;
                    $stats['mirrored']++;
                } elseif ($result === 'pending_face') {
                    $stats['accepted']++;
                    $stats['pending_face']++;
                } else {
                    $stats['accepted']++;
                    $stats['errors']++;
                }
            } catch (Throwable $exception) {
                $stats['errors']++;
                Log::error('biometric.adms.attlog_ingest_failed', [
                    'serial' => $device->serial_number,
                    'line' => $line,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if ($stamp !== null && $stamp !== '') {
            $device->touchSeen($stamp, null);
        } else {
            $device->touchSeen();
        }

        if ($stats['mirrored'] > 0 && config('biometric.process_inline', true)) {
            try {
                $this->processor->processPending();
            } catch (Throwable $exception) {
                Log::warning('biometric.adms.process_inline_failed', [
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @param  array{user_pin: string, punched_at: Carbon, punch_status: ?int, verify_type: ?int, work_code: ?string}  $parsed
     */
    protected function storePunch(BiometricDevice $device, array $parsed, string $rawLine, array $meta): string
    {
        $existing = BiometricPunch::query()
            ->where('serial_number', $device->serial_number)
            ->where('user_pin', $parsed['user_pin'])
            ->where('punched_at', $parsed['punched_at']->format('Y-m-d H:i:s'))
            ->where('punch_status', $parsed['punch_status'])
            ->first();

        if ($existing) {
            return 'duplicate';
        }

        $gateFace = $this->faceVerify->shouldGateDevice($device);

        $punch = DB::transaction(function () use ($device, $parsed, $rawLine, $meta, $gateFace): BiometricPunch|string {
            $punch = BiometricPunch::query()->create([
                'biometric_device_id' => $device->id,
                'serial_number' => $device->serial_number,
                'user_pin' => $parsed['user_pin'],
                'punched_at' => $parsed['punched_at'],
                'punch_status' => $parsed['punch_status'],
                'verify_type' => $parsed['verify_type'],
                'work_code' => $parsed['work_code'],
                'process_status' => BiometricPunch::STATUS_PENDING,
                'raw_line' => $rawLine,
                'raw_payload' => $meta,
            ]);

            $device->recordPunchReceived();

            if ($gateFace) {
                return $punch;
            }

            $punchLogId = $this->mirrorToPunchLogs($device, $parsed);

            if ($punchLogId === null) {
                $punch->update([
                    'process_status' => BiometricPunch::STATUS_FAILED,
                    'process_error' => 'Could not write punch_logs row.',
                ]);

                return 'failed';
            }

            $punch->update([
                'punch_log_id' => $punchLogId,
                'process_status' => BiometricPunch::STATUS_MIRRORED,
                'process_error' => null,
            ]);

            return 'mirrored';
        });

        if (! $gateFace) {
            return is_string($punch) ? $punch : 'failed';
        }

        /** @var BiometricPunch $punch */
        $faceResult = $this->faceVerify->holdPunchForVerification(
            $device,
            $punch,
            $parsed['user_pin'],
            $parsed['punched_at'],
        );

        return $faceResult === 'pending' ? 'pending_face' : $faceResult;
    }

    /**
     * @param  array{user_pin: string, punched_at: Carbon, punch_status: ?int, verify_type: ?int, work_code: ?string}  $parsed
     */
    protected function mirrorToPunchLogs(BiometricDevice $device, array $parsed): ?int
    {
        $table = $this->punchLogs->punchTable();

        if (! Schema::hasTable($table)) {
            return null;
        }

        $employeeId = $this->punchLogs->normalizeRoll($parsed['user_pin']);
        $date = $parsed['punched_at']->toDateString();
        $time = $parsed['punched_at']->format('H:i:s');

        $duplicate = DB::table($table)
            ->where('employee_id', $employeeId)
            ->where('punch_date', $date)
            ->where('punch_time', $time)
            ->when(Schema::hasColumn($table, 'device_name'), fn ($q) => $q->where('device_name', $device->name))
            ->exists();

        if ($duplicate) {
            $id = DB::table($table)
                ->where('employee_id', $employeeId)
                ->where('punch_date', $date)
                ->where('punch_time', $time)
                ->value('id');

            return $id ? (int) $id : null;
        }

        $payload = [
            'employee_id' => $employeeId,
            'punch_date' => $date,
            'punch_time' => $time,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn($table, 'device_name')) {
            $payload['device_name'] = $device->name;
        }

        if (Schema::hasColumn($table, 'area_name')) {
            $payload['area_name'] = $device->location;
        }

        if (Schema::hasColumn($table, 'is_manual')) {
            $payload['is_manual'] = false;
        }

        return (int) DB::table($table)->insertGetId($payload);
    }

    /**
     * @return array{user_pin: string, punched_at: Carbon, punch_status: ?int, verify_type: ?int, work_code: ?string}|null
     */
    protected function parseAttLogLine(string $line): ?array
    {
        $parts = preg_split("/\t+/", $line) ?: [];

        if (count($parts) < 2) {
            $parts = preg_split('/\s{2,}|\s/', $line) ?: [];
        }

        $pin = trim((string) ($parts[0] ?? ''));
        $when = trim((string) ($parts[1] ?? ''));

        if ($pin === '' || $when === '') {
            return null;
        }

        try {
            $punchedAt = Carbon::parse($when, config('biometric.timezone', config('app.timezone')));
        } catch (Throwable) {
            return null;
        }

        return [
            'user_pin' => $pin,
            'punched_at' => $punchedAt,
            'punch_status' => isset($parts[2]) && is_numeric($parts[2]) ? (int) $parts[2] : null,
            'verify_type' => isset($parts[3]) && is_numeric($parts[3]) ? (int) $parts[3] : null,
            'work_code' => isset($parts[4]) ? trim((string) $parts[4]) : null,
        ];
    }
}
