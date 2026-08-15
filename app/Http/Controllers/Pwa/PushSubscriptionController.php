<?php

namespace App\Http\Controllers\Pwa;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\WebPushService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function publicKey(WebPushService $push): JsonResponse
    {
        if (! $push->isConfigured()) {
            return response()->json(['enabled' => false, 'publicKey' => null]);
        }

        return response()->json([
            'enabled' => true,
            'publicKey' => $push->publicKey(),
        ]);
    }

    public function store(Request $request, WebPushService $push): JsonResponse
    {
        abort_unless($push->isConfigured(), 503, 'Web push is not configured.');

        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
            'keys.p256dh' => ['nullable', 'string', 'max:255'],
            'keys.auth' => ['nullable', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:32'],
        ]);

        $user = $request->user();
        $studentId = $request->session()->get('student_portal_id');
        $student = $studentId ? Student::query()->find($studentId) : null;

        abort_unless($user || $student, 401);

        $audience = $user ? 'staff' : 'portal';

        $push->saveSubscription($data, $user, $student, $audience);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, WebPushService $push): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'string', 'max:500'],
        ]);

        $push->forgetEndpoint($data['endpoint']);

        return response()->json(['ok' => true]);
    }
}
