<?php

namespace App\Http\Controllers\Webhooks;

use App\Services\MetaWhatsAppWebhookService;
use App\Support\MetaWhatsAppWebhookTrace;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
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
            MetaWhatsAppWebhookTrace::write('signature_rejected');

            abort(403, 'Invalid webhook signature.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();

        MetaWhatsAppWebhookTrace::write('webhook_post', [
            'messages' => MetaWhatsAppWebhookTrace::summarizeInboundTypes($payload),
        ]);

        try {
            $webhook->process($payload);
        } catch (\Throwable $exception) {
            Log::error('Meta WhatsApp webhook processing failed', [
                'error' => $exception->getMessage(),
            ]);

            MetaWhatsAppWebhookTrace::write('process_failed', [
                'error' => $exception->getMessage(),
            ]);
        }

        return response()->json(['success' => true]);
    }
}
