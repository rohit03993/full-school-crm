<?php

namespace App\Services\FaceVerify;

use App\Models\FaceVerificationRequest;
use App\Models\Student;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class FaceVerifyClient
{
    /**
     * @return array<string, mixed>
     */
    public function createVerificationRequest(FaceVerificationRequest $request): array
    {
        $payload = [
            'enrollment_number' => $request->enrollment_number,
            'device_id' => $request->face_device_id,
            'crm_request_id' => $request->id,
            'meta' => array_filter([
                'source' => 'adms',
                'biometric_punch_id' => $request->biometric_punch_id,
                'serial' => $request->biometricDevice?->serial_number,
                'user_pin' => $request->enrollment_number,
            ], fn (mixed $value): bool => $value !== null && $value !== ''),
        ];

        return $this->request()
            ->post('/verification-requests', $payload)
            ->throw()
            ->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function upsertStudent(Student $student): array
    {
        $enrollment = $student->activeEnrollment;

        if (! $enrollment || blank($enrollment->enrollment_number)) {
            throw new InvalidArgumentException('Student has no active enrollment number.');
        }

        $payload = [
            'enrollment_number' => strtoupper(trim($enrollment->enrollment_number)),
            'name' => $student->name,
            'batch' => $student->activeBatchStudent?->batch?->name
                ?? $enrollment->course?->name
                ?? $enrollment->academicSession?->name,
            'crm_student_id' => (string) $student->id,
        ];

        return $this->request()
            ->post('/students', $payload)
            ->throw()
            ->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getRequestStatus(string $faceRequestId): array
    {
        return $this->request()
            ->get('/verification-requests/'.rawurlencode($faceRequestId))
            ->throw()
            ->json() ?? [];
    }

    /**
     * @return array<string, mixed>
     */
    public function health(): array
    {
        return $this->baseRequest()
            ->get('/health')
            ->throw()
            ->json() ?? [];
    }

    public function isConfigured(): bool
    {
        return filled(config('face_verify.api_url')) && filled(config('face_verify.service_token'));
    }

    public function faceRequestIdFromResponse(array $response): ?string
    {
        $value = Arr::get($response, 'request_id')
            ?? Arr::get($response, 'id')
            ?? Arr::get($response, 'data.request_id');

        return filled($value) ? (string) $value : null;
    }

    protected function request(): PendingRequest
    {
        if (! $this->isConfigured()) {
            throw new InvalidArgumentException('Face Verify API URL or service token is not configured.');
        }

        return $this->baseRequest()
            ->withToken((string) config('face_verify.service_token'));
    }

    protected function baseRequest(): PendingRequest
    {
        $baseUrl = rtrim((string) config('face_verify.api_url'), '/');

        if ($baseUrl === '') {
            throw new InvalidArgumentException('Face Verify API URL is not configured.');
        }

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->timeout(max(1, (int) config('face_verify.http_timeout_seconds', 10)));
    }
}
