<?php

namespace App\Services\FaceVerify;

use App\Models\BiometricDevice;
use App\Models\BiometricPunch;
use App\Models\FaceVerificationRequest;
use App\Models\Student;
use App\Services\Punch\PunchAttendanceProcessor;
use App\Services\Punch\PunchLogService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class FaceVerifyGateService
{
    public function __construct(
        protected FaceVerifyClient $client,
        protected PunchLogService $punchLogs,
        protected PunchAttendanceProcessor $processor,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('face_verify.enabled', false);
    }

    public function shouldGateDevice(BiometricDevice $device): bool
    {
        return $this->isEnabled()
            && $this->client->isConfigured()
            && (bool) $device->requires_face_verify;
    }

    public function resolveFaceDeviceId(BiometricDevice $device): ?string
    {
        $deviceId = trim((string) ($device->face_verify_device_id ?: config('face_verify.default_device_id')));

        return $deviceId !== '' ? $deviceId : null;
    }

    /**
     * Hold the biometric punch until Face Verify returns PASS.
     *
     * @return 'pending'|'ignored'|'failed'
     */
    public function holdPunchForVerification(
        BiometricDevice $device,
        BiometricPunch $punch,
        string $userPin,
        Carbon $punchedAt,
    ): string {
        $enrollmentNumber = $this->punchLogs->normalizeRoll($userPin);
        $student = $this->punchLogs->findStudentByRoll($enrollmentNumber);
        $faceDeviceId = $this->resolveFaceDeviceId($device);

        if (! $student) {
            $punch->update([
                'process_status' => BiometricPunch::STATUS_IGNORED,
                'process_error' => 'Face verify skipped: enrollment number not found.',
            ]);

            return 'ignored';
        }

        if (! $faceDeviceId) {
            $punch->update([
                'process_status' => BiometricPunch::STATUS_FAILED,
                'process_error' => 'Face verify device ID is not configured for this biometric device.',
            ]);

            return 'failed';
        }

        $request = FaceVerificationRequest::query()->create([
            'id' => (string) Str::uuid(),
            'biometric_punch_id' => $punch->id,
            'biometric_device_id' => $device->id,
            'student_id' => $student->id,
            'enrollment_number' => $enrollmentNumber,
            'face_device_id' => $faceDeviceId,
            'status' => FaceVerificationRequest::STATUS_PENDING,
            'punched_at' => $punchedAt,
            'requested_at' => now(),
            'meta' => [
                'source' => 'adms',
                'serial' => $device->serial_number,
                'user_pin' => $userPin,
            ],
        ]);

        try {
            $response = $this->client->createVerificationRequest($request->loadMissing('biometricDevice'));
            $faceRequestId = $this->client->faceRequestIdFromResponse($response);

            $request->update([
                'face_request_id' => $faceRequestId,
                'face_student_id' => data_get($response, 'student_id') ?: data_get($response, 'data.student_id'),
                'meta' => array_merge($request->meta ?? [], [
                    'face_api_response' => $response,
                ]),
            ]);

            $punch->update([
                'process_status' => BiometricPunch::STATUS_PENDING,
                'process_error' => null,
            ]);

            return 'pending';
        } catch (Throwable $exception) {
            Log::error('face_verify.verification_request_failed', [
                'crm_request_id' => $request->id,
                'serial' => $device->serial_number,
                'enrollment_number' => $enrollmentNumber,
                'message' => $exception->getMessage(),
            ]);

            $request->update([
                'status' => FaceVerificationRequest::STATUS_ERROR,
                'responded_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);

            $punch->update([
                'process_status' => BiometricPunch::STATUS_FAILED,
                'process_error' => 'Face verify request failed: '.$exception->getMessage(),
            ]);

            return 'failed';
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, already_processed?: bool, message?: string}
     */
    public function approvePass(array $payload): array
    {
        $status = strtoupper(trim((string) ($payload['status'] ?? '')));

        if ($status !== FaceVerificationRequest::STATUS_PASS) {
            return ['ok' => false, 'message' => 'Only PASS callbacks are accepted.'];
        }

        $request = $this->findRequest(
            crmRequestId: isset($payload['crm_request_id']) ? (string) $payload['crm_request_id'] : null,
            faceRequestId: isset($payload['request_id']) ? (string) $payload['request_id'] : null,
        );

        if (! $request) {
            return ['ok' => false, 'message' => 'Verification request not found.'];
        }

        if ($request->status === FaceVerificationRequest::STATUS_PASS && filled($request->biometricPunch?->punch_log_id)) {
            return ['ok' => true, 'already_processed' => true];
        }

        return DB::transaction(function () use ($request, $payload): array {
            $locked = FaceVerificationRequest::query()
                ->whereKey($request->id)
                ->lockForUpdate()
                ->first();

            if (! $locked) {
                return ['ok' => false, 'message' => 'Verification request not found.'];
            }

            $punch = $locked->biometricPunch()->lockForUpdate()->first();

            if ($locked->status === FaceVerificationRequest::STATUS_PASS && filled($punch?->punch_log_id)) {
                return ['ok' => true, 'already_processed' => true];
            }

            $device = $locked->biometricDevice;
            $enrollmentNumber = $this->punchLogs->normalizeRoll(
                (string) ($payload['enrollment_number'] ?? $locked->enrollment_number),
            );
            $punchedAt = $locked->punched_at ?? now();

            $punchLogId = $this->writePunchLog(
                enrollmentNumber: $enrollmentNumber,
                punchedAt: $punchedAt,
                deviceName: $device?->name,
                areaName: $device?->location,
            );

            if ($punchLogId === null) {
                return ['ok' => false, 'message' => 'Could not write punch_logs row.'];
            }

            $locked->update([
                'status' => FaceVerificationRequest::STATUS_PASS,
                'face_request_id' => $payload['request_id'] ?? $locked->face_request_id,
                'face_student_id' => $payload['student_id'] ?? $locked->face_student_id,
                'score' => isset($payload['score']) ? (float) $payload['score'] : $locked->score,
                'responded_at' => now(),
                'error_message' => null,
                'meta' => array_merge($locked->meta ?? [], [
                    'callback_payload' => $payload,
                ]),
            ]);

            if ($punch) {
                $punch->update([
                    'punch_log_id' => $punchLogId,
                    'process_status' => BiometricPunch::STATUS_MIRRORED,
                    'process_error' => null,
                ]);
            }

            return ['ok' => true, 'already_processed' => false];
        });
    }

    /**
     * Camera-first attendance: kiosk matched a face (1:N) with no RFID punch.
     * Leaves ADMS / biometric machines completely untouched.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     ok: bool,
     *     already_processed?: bool,
     *     message?: string,
     *     face_verification_request_id?: string,
     *     punch_log_id?: int
     * }
     */
    public function recordCameraPunch(array $payload): array
    {
        $enrollmentNumber = $this->punchLogs->normalizeRoll((string) ($payload['enrollment_number'] ?? ''));
        $score = isset($payload['score']) ? (float) $payload['score'] : null;
        $faceDeviceId = trim((string) ($payload['device_id'] ?? ''));
        $punchedAt = $this->parseTimestamp($payload['timestamp'] ?? null) ?? now();

        if ($enrollmentNumber === '') {
            return ['ok' => false, 'message' => 'enrollment_number is required.'];
        }

        $student = $this->punchLogs->findStudentByRoll($enrollmentNumber);

        if (! $student) {
            return ['ok' => false, 'message' => 'Student not found.'];
        }

        $cooldownSeconds = max(0, (int) config('face_verify.camera_punch_cooldown_seconds', 60));

        if ($cooldownSeconds > 0) {
            $recent = FaceVerificationRequest::query()
                ->where('enrollment_number', $enrollmentNumber)
                ->where('status', FaceVerificationRequest::STATUS_PASS)
                ->where('meta->source', 'camera_kiosk')
                ->where('responded_at', '>=', now()->subSeconds($cooldownSeconds))
                ->latest('responded_at')
                ->first();

            if ($recent) {
                return [
                    'ok' => true,
                    'already_processed' => true,
                    'face_verification_request_id' => $recent->id,
                    'message' => 'Duplicate camera punch within cooldown window.',
                ];
            }
        }

        $device = $faceDeviceId !== ''
            ? BiometricDevice::query()->where('face_verify_device_id', $faceDeviceId)->first()
            : null;

        // Camera punches always use a distinct device label so they never look like ZKTeco ADMS punches.
        $deviceName = 'Face Camera Kiosk';
        $areaName = $device?->location;

        return DB::transaction(function () use ($payload, $student, $enrollmentNumber, $score, $faceDeviceId, $punchedAt, $device, $deviceName, $areaName): array {
            $punchLogId = $this->writePunchLog(
                enrollmentNumber: $enrollmentNumber,
                punchedAt: $punchedAt,
                deviceName: $deviceName,
                areaName: $areaName,
            );

            if ($punchLogId === null) {
                return ['ok' => false, 'message' => 'Could not write punch_logs row.'];
            }

            $request = FaceVerificationRequest::query()->create([
                'id' => (string) Str::uuid(),
                'biometric_punch_id' => null,
                'biometric_device_id' => $device?->id,
                'student_id' => $student->id,
                'enrollment_number' => $enrollmentNumber,
                'face_device_id' => $faceDeviceId !== '' ? $faceDeviceId : null,
                'face_request_id' => isset($payload['request_id']) ? (string) $payload['request_id'] : null,
                'face_student_id' => isset($payload['student_id']) ? (string) $payload['student_id'] : null,
                'status' => FaceVerificationRequest::STATUS_PASS,
                'score' => $score,
                'punched_at' => $punchedAt,
                'requested_at' => now(),
                'responded_at' => now(),
                'meta' => [
                    'source' => 'camera_kiosk',
                    'callback_payload' => $payload,
                ],
            ]);

            return [
                'ok' => true,
                'already_processed' => false,
                'face_verification_request_id' => $request->id,
                'punch_log_id' => $punchLogId,
            ];
        });
    }

    public function processAttendanceAfterPass(): void
    {
        try {
            $this->processor->processPending();
        } catch (Throwable $exception) {
            Log::warning('face_verify.process_pending_failed', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    protected function parseTimestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    public function markTimedOutPending(): int
    {
        $timeoutSeconds = max(1, (int) config('face_verify.timeout_seconds', 30));
        $cutoff = now()->subSeconds($timeoutSeconds);

        $pending = FaceVerificationRequest::query()
            ->where('status', FaceVerificationRequest::STATUS_PENDING)
            ->where(function ($query) use ($cutoff): void {
                $query->where('requested_at', '<=', $cutoff)
                    ->orWhere(function ($query) use ($cutoff): void {
                        $query->whereNull('requested_at')
                            ->where('created_at', '<=', $cutoff);
                    });
            })
            ->get();

        $count = 0;

        foreach ($pending as $request) {
            $request->update([
                'status' => FaceVerificationRequest::STATUS_TIMEOUT,
                'responded_at' => now(),
                'error_message' => 'Verification timed out waiting for kiosk response.',
            ]);

            $request->biometricPunch?->update([
                'process_status' => BiometricPunch::STATUS_IGNORED,
                'process_error' => 'Face verify timed out; no attendance marked.',
            ]);

            $count++;
        }

        return $count;
    }

    public function syncStudent(Student $student): array
    {
        $student->loadMissing([
            'activeEnrollment.course',
            'activeEnrollment.academicSession',
            'activeBatchStudent.batch',
        ]);

        return $this->client->upsertStudent($student);
    }

    /**
     * @param  iterable<Student>  $students
     * @return array{synced: int}
     */
    public function syncStudents(iterable $students): array
    {
        $ready = [];

        foreach ($students as $student) {
            $student->loadMissing([
                'activeEnrollment.course',
                'activeEnrollment.academicSession',
                'activeBatchStudent.batch',
            ]);
            $ready[] = $student;
        }

        return $this->client->upsertStudents($ready);
    }

    protected function findRequest(?string $crmRequestId, ?string $faceRequestId): ?FaceVerificationRequest
    {
        if (filled($crmRequestId)) {
            $byCrm = FaceVerificationRequest::query()->whereKey($crmRequestId)->first();

            if ($byCrm) {
                return $byCrm;
            }
        }

        if (filled($faceRequestId)) {
            return FaceVerificationRequest::query()
                ->where('face_request_id', $faceRequestId)
                ->first();
        }

        return null;
    }

    protected function writePunchLog(
        string $enrollmentNumber,
        Carbon $punchedAt,
        ?string $deviceName,
        ?string $areaName,
    ): ?int {
        $table = $this->punchLogs->punchTable();

        if (! Schema::hasTable($table)) {
            return null;
        }

        $employeeId = $this->punchLogs->normalizeRoll($enrollmentNumber);
        $date = $punchedAt->toDateString();
        $time = $punchedAt->format('H:i:s');

        $duplicateQuery = DB::table($table)
            ->where('employee_id', $employeeId)
            ->where('punch_date', $date)
            ->where('punch_time', $time);

        if (Schema::hasColumn($table, 'device_name') && filled($deviceName)) {
            $duplicateQuery->where('device_name', $deviceName);
        }

        $existingId = $duplicateQuery->value('id');

        if ($existingId) {
            return (int) $existingId;
        }

        $payload = [
            'employee_id' => $employeeId,
            'punch_date' => $date,
            'punch_time' => $time,
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (Schema::hasColumn($table, 'device_name')) {
            $payload['device_name'] = $deviceName;
        }

        if (Schema::hasColumn($table, 'area_name')) {
            $payload['area_name'] = $areaName;
        }

        if (Schema::hasColumn($table, 'is_manual')) {
            $payload['is_manual'] = false;
        }

        return (int) DB::table($table)->insertGetId($payload);
    }
}
