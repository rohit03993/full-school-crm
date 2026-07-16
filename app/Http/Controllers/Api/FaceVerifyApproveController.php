<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FaceVerify\FaceVerifyGateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class FaceVerifyApproveController extends Controller
{
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
        $result = $gate->approvePass($payload);

        if (! ($result['ok'] ?? false)) {
            $status = ($result['message'] ?? '') === 'Verification request not found.' ? 404 : 422;

            return response()->json($result, $status);
        }

        if (! ($result['already_processed'] ?? false)) {
            $gate->processAttendanceAfterPass();
        }

        Log::info('face_verify.approve_callback', [
            'crm_request_id' => $payload['crm_request_id'] ?? null,
            'request_id' => $payload['request_id'] ?? null,
            'already_processed' => $result['already_processed'] ?? false,
        ]);

        return response()->json(['ok' => true]);
    }

    protected function bearerTokenMatches(Request $request): bool
    {
        $expected = (string) config('face_verify.service_token');
        $provided = (string) $request->bearerToken();

        return $expected !== '' && $provided !== '' && hash_equals($expected, $provided);
    }

    protected function signatureMatches(string $rawBody, string $signature): bool
    {
        $secret = (string) config('face_verify.callback_secret');

        if ($secret === '' || $signature === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawBody, $secret);

        return hash_equals($expected, $signature);
    }
}
