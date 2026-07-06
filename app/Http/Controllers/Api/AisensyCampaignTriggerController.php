<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppIntegrationApiService;
use App\Services\WhatsAppLiveCampaignService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AisensyCampaignTriggerController extends Controller
{
    public function __invoke(
        Request $request,
        WhatsAppIntegrationApiService $apiKeys,
        WhatsAppLiveCampaignService $liveCampaigns,
    ): JsonResponse {
        $payload = $request->validate([
            'apiKey' => ['required', 'string', 'min:8'],
            'campaignName' => ['required', 'string', 'max:120'],
            'destination' => ['required', 'string', 'min:8', 'max:20'],
            'userName' => ['nullable', 'string', 'max:120'],
            'templateParams' => ['nullable', 'array'],
            'templateParams.*' => ['nullable'],
        ]);

        if (! $apiKeys->validateKey($payload['apiKey'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key.',
            ], 401);
        }

        $templateParams = is_array($payload['templateParams'] ?? null)
            ? array_values($payload['templateParams'])
            : [];

        $result = $liveCampaigns->triggerByName(
            $payload['campaignName'],
            $payload['destination'],
            $payload['userName'] ?? null,
            $templateParams,
        );

        if ($result['status'] !== 'success') {
            $status = str_contains((string) ($result['error'] ?? ''), 'No live API campaign')
                ? 404
                : 400;

            return response()->json([
                'success' => false,
                'message' => (string) ($result['error'] ?? 'Send failed.'),
            ], $status);
        }

        return response()->json([
            'success' => true,
            'message' => 'Campaign triggered successfully',
            'campaign_id' => isset($result['campaign_id']) ? (string) $result['campaign_id'] : null,
            'message_id' => $result['message_id'] ?? null,
        ]);
    }
}
