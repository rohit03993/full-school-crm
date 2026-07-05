<?php

namespace App\Http\Controllers\Webhooks;

use App\Services\MetaWhatsAppWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class MetaWhatsAppWebhookController
{
    public function __invoke(Request $request, MetaWhatsAppWebhookService $webhook): Response|SymfonyResponse
    {
        if ($request->isMethod('GET')) {
            $challenge = $webhook->verifySubscription($request);

            if ($challenge === null) {
                abort(403, 'Webhook verification failed.');
            }

            return response($challenge, 200, ['Content-Type' => 'text/plain']);
        }

        if (! $webhook->verifySignature($request)) {
            abort(403, 'Invalid webhook signature.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $webhook->process($payload);

        return response()->json(['success' => true]);
    }
}
