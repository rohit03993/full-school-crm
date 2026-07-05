<?php

namespace App\Services;

use App\Enums\MetaWhatsAppMessageStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppWebhookService
{
    public function __construct(
        protected MetaWhatsAppService $meta,
        protected MetaWhatsAppMessageLogger $logger,
    ) {}

    public function verifySubscription(Request $request): ?string
    {
        $mode = (string) $request->query('hub_mode', '');
        $token = (string) $request->query('hub_verify_token', '');
        $challenge = (string) $request->query('hub_challenge', '');

        if ($mode !== 'subscribe' || $challenge === '') {
            return null;
        }

        $expected = $this->meta->verifyToken();

        if (blank($expected) || ! hash_equals($expected, $token)) {
            Log::warning('Meta WhatsApp webhook verification failed — token mismatch.');

            return null;
        }

        return $challenge;
    }

    public function verifySignature(Request $request): bool
    {
        $secret = $this->meta->appSecret();

        if (blank($secret)) {
            Log::warning('Meta WhatsApp webhook rejected — app secret not configured.');

            return false;
        }

        $header = (string) $request->header('X-Hub-Signature-256', '');

        if (! str_starts_with($header, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) $secret);

        return hash_equals($expected, $header);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function process(array $payload): void
    {
        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $value = $change['value'] ?? null;

                if (! is_array($value)) {
                    continue;
                }

                $this->processStatuses($value);
                $this->processInboundMessages($value);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function processStatuses(array $value): void
    {
        foreach ($value['statuses'] ?? [] as $statusRow) {
            if (! is_array($statusRow)) {
                continue;
            }

            $wamid = (string) ($statusRow['id'] ?? '');

            if ($wamid === '') {
                continue;
            }

            $status = MetaWhatsAppMessageStatus::fromWebhookStatus((string) ($statusRow['status'] ?? ''));

            if ($status === null) {
                continue;
            }

            $detail = null;

            if ($status === MetaWhatsAppMessageStatus::Failed) {
                $detail = (string) data_get($statusRow, 'errors.0.title', data_get($statusRow, 'errors.0.message', 'Delivery failed'));
            }

            $this->logger->applyWebhookStatus($wamid, $status, $detail, $statusRow);
        }
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function processInboundMessages(array $value): void
    {
        foreach ($value['messages'] ?? [] as $message) {
            if (! is_array($message)) {
                continue;
            }

            $from = (string) ($message['from'] ?? '');
            $wamid = (string) ($message['id'] ?? '');
            $type = (string) ($message['type'] ?? 'text');
            $preview = match ($type) {
                'text' => (string) data_get($message, 'text.body', ''),
                'button' => (string) data_get($message, 'button.text', '[button]'),
                'interactive' => '[interactive message]',
                default => '['.$type.' message]',
            };

            if ($from === '') {
                continue;
            }

            $this->logger->recordInbound($from, $wamid !== '' ? $wamid : null, $preview, $message);
        }
    }
}
