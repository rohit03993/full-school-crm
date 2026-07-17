<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthenticatesFaceVerifyCallbacks;
use App\Http\Controllers\Controller;
use App\Services\FaceVerify\FaceVerifyGateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FaceVerifyCameraPunchController extends Controller
{
    use AuthenticatesFaceVerifyCallbacks;

    /**
     * Camera-first attendance: kiosk identified a face (1:N) and asks CRM to mark punch.
     * Does not touch RFID / ADMS biometric machines.
     */
    public function __invoke(Request $request, FaceVerifyGateService $gate): JsonResponse
    {
        if (! $gate->isEnabled()) {
            return response()->json(['ok' => false, 'message' => 'Face Verify is disabled.'], 503);
        }

        if (! $this->bearerTokenMatches($request)) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized.'], 401);
        }

        $rawBody = $request->getContent();

        if (! $this->signatureMatches($rawBody, (string) $request->header('X-Face-Verify-Signature'))) {
            return response()->json(['ok' => false, 'message' => 'Invalid signature.'], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $result = $gate->recordCameraPunch($payload);

        if (! ($result['ok'] ?? false)) {
            $status = match ($result['message'] ?? '') {
                'Student not found.' => 404,
                default => 422,
            };

            return response()->json($result, $status);
        }

        if (! ($result['already_processed'] ?? false)) {
            $gate->processAttendanceAfterPass();
        }

        Log::info('face_verify.camera_punch', [
            'enrollment_number' => $payload['enrollment_number'] ?? null,
            'device_id' => $payload['device_id'] ?? null,
            'score' => $payload['score'] ?? null,
            'already_processed' => $result['already_processed'] ?? false,
        ]);

        return response()->json([
            'ok' => true,
            'already_processed' => $result['already_processed'] ?? false,
            'face_verification_request_id' => $result['face_verification_request_id'] ?? null,
            'punch_log_id' => $result['punch_log_id'] ?? null,
        ]);
    }
}
