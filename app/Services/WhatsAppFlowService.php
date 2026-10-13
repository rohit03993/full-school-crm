<?php

namespace App\Services;

use App\Enums\LicenseFeature;
use App\Models\Setting;
use App\Support\FeatureGate;
use App\Support\WhatsAppCampusVisitFlowJson;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppFlowService
{
    public const FLOW_ID_SETTING = 'whatsapp.campus_visit_flow_id';

    public function __construct(
        protected MetaWhatsAppService $meta,
    ) {}

    public function savedFlowId(): ?string
    {
        $saved = trim((string) Setting::getValue(self::FLOW_ID_SETTING, ''));

        return $saved !== '' ? $saved : null;
    }

    /**
     * @return array{status: string, items?: list<array<string, mixed>>, error?: string}
     */
    public function listFlows(): array
    {
        $ready = $this->managementReady();

        if ($ready !== null) {
            return $ready;
        }

        try {
            $response = Http::timeout(30)
                ->withToken((string) $this->meta->accessToken())
                ->acceptJson()
                ->get($this->meta->graphUrl($this->meta->wabaId().'/flows'));

            $data = $response->json();

            if (! $response->successful() || ! is_array($data)) {
                return [
                    'status' => 'failed',
                    'error' => $this->meta->parseApiError($data, $response->body()),
                ];
            }

            $items = collect($data['data'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->values()
                ->all();

            return ['status' => 'success', 'items' => $items];
        } catch (\Throwable $exception) {
            Log::error('WhatsApp flow list failed', ['error' => $exception->getMessage()]);

            return ['status' => 'failed', 'error' => $exception->getMessage()];
        }
    }

    /**
     * @return array{status: string, flow_id?: string, error?: string}
     */
    public function publishCampusVisit(): array
    {
        $listed = $this->listFlows();

        if ($listed['status'] !== 'success') {
            return [
                'status' => 'failed',
                'error' => (string) ($listed['error'] ?? 'WhatsApp did not return the form list.'),
            ];
        }

        $flowId = $this->existingFlowId($listed['items'] ?? []);

        if ($flowId === null) {
            $created = $this->createCampusVisitDraft();

            if ($created['status'] !== 'success') {
                return $created;
            }

            $flowId = $created['flow_id'] ?? null;
        }

        if ($flowId === null || $flowId === '') {
            return ['status' => 'failed', 'error' => 'WhatsApp did not return a form id.'];
        }

        $uploaded = $this->uploadFlowJson($flowId);

        if ($uploaded['status'] !== 'success') {
            return $uploaded;
        }

        $published = $this->publishFlow($flowId);

        if ($published['status'] !== 'success') {
            return $published;
        }

        Setting::setValue(self::FLOW_ID_SETTING, $flowId, 'whatsapp');

        return ['status' => 'success', 'flow_id' => $flowId];
    }

    /**
     * @return array{status: string, error?: string, message_id?: string, response?: mixed}
     */
    public function sendCampusVisit(string $phone): array
    {
        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            return ['status' => 'failed', 'error' => 'WhatsApp module is not enabled.'];
        }

        if (! $this->meta->isConfigured()) {
            return ['status' => 'failed', 'error' => 'WhatsApp is not configured. Open Connection & Setup and save credentials.'];
        }

        $flowId = $this->savedFlowId();

        if ($flowId === null) {
            return ['status' => 'failed', 'error' => 'The campus visit form is not published yet.'];
        }

        $destination = $this->meta->destinationE164($phone);

        if ($destination === null) {
            return ['status' => 'failed', 'error' => 'Invalid Indian mobile number.'];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $destination,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'flow',
                'header' => [
                    'type' => 'text',
                    'text' => 'Campus visit',
                ],
                'body' => [
                    'text' => 'Tap the button, choose a course and a date, then tap Finish.',
                ],
                'action' => [
                    'name' => 'flow',
                    'parameters' => [
                        'flow_message_version' => '3',
                        'flow_token' => WhatsAppCampusVisitFlowJson::FLOW_TOKEN,
                        'flow_id' => $flowId,
                        'flow_cta' => 'Book visit',
                        'flow_action' => 'navigate',
                        'flow_action_payload' => [
                            'screen' => WhatsAppCampusVisitFlowJson::SCREEN_ID,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::timeout(30)
                ->withToken((string) $this->meta->accessToken())
                ->acceptJson()
                ->post($this->meta->graphUrl($this->meta->phoneNumberId().'/messages'), $payload);

            return $this->messageResult($response);
        } catch (\Throwable $exception) {
            Log::error('WhatsApp visit form send failed', ['error' => $exception->getMessage()]);

            return ['status' => 'failed', 'error' => $exception->getMessage()];
        }
    }

    /**
     * @return array{status: string, flow_id?: string, error?: string}
     */
    public function publishNamedFlow(string $flowName, string $json, ?string $existingFlowId = null): array
    {
        $listed = $this->listFlows();

        if ($listed['status'] !== 'success') {
            return [
                'status' => 'failed',
                'error' => (string) ($listed['error'] ?? 'WhatsApp did not return the form list.'),
            ];
        }

        $flowId = filled($existingFlowId) ? trim((string) $existingFlowId) : null;

        if ($flowId === null || $flowId === '') {
            $flowId = $this->flowIdNamed($listed['items'] ?? [], $flowName);
        }

        if ($flowId === null) {
            $created = $this->createNamedDraft($flowName);

            if ($created['status'] !== 'success') {
                return $created;
            }

            $flowId = $created['flow_id'] ?? null;
        }

        if ($flowId === null || $flowId === '') {
            return ['status' => 'failed', 'error' => 'WhatsApp did not return a form id.'];
        }

        $uploaded = $this->uploadFlowJson($flowId, $json);

        if ($uploaded['status'] !== 'success') {
            return $uploaded;
        }

        $published = $this->publishFlow($flowId);

        if ($published['status'] !== 'success') {
            return $published;
        }

        return ['status' => 'success', 'flow_id' => $flowId];
    }

    /**
     * @return array{status: string, error?: string, message_id?: string, response?: mixed}
     */
    public function sendInteractiveFlow(
        string $phone,
        string $flowId,
        string $flowToken,
        string $screenId,
        string $header,
        string $body,
        string $buttonLabel,
    ): array {
        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            return ['status' => 'failed', 'error' => 'WhatsApp module is not enabled.'];
        }

        if (! $this->meta->isConfigured()) {
            return ['status' => 'failed', 'error' => 'WhatsApp is not configured. Open Connection & Setup and save credentials.'];
        }

        $destination = $this->meta->destinationE164($phone);

        if ($destination === null) {
            return ['status' => 'failed', 'error' => 'Invalid Indian mobile number.'];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $destination,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'flow',
                'header' => [
                    'type' => 'text',
                    'text' => mb_substr(trim($header), 0, 60),
                ],
                'body' => [
                    'text' => mb_substr(trim($body), 0, 1024),
                ],
                'action' => [
                    'name' => 'flow',
                    'parameters' => [
                        'flow_message_version' => '3',
                        'flow_token' => $flowToken,
                        'flow_id' => $flowId,
                        'flow_cta' => mb_substr(trim($buttonLabel), 0, 20) ?: 'Open form',
                        'flow_action' => 'navigate',
                        'flow_action_payload' => [
                            'screen' => $screenId,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = Http::timeout(30)
                ->withToken((string) $this->meta->accessToken())
                ->acceptJson()
                ->post($this->meta->graphUrl($this->meta->phoneNumberId().'/messages'), $payload);

            return $this->messageResult($response);
        } catch (\Throwable $exception) {
            Log::error('WhatsApp in-chat form send failed', ['error' => $exception->getMessage()]);

            return ['status' => 'failed', 'error' => $exception->getMessage()];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function existingFlowId(array $items): ?string
    {
        foreach ($items as $item) {
            if ((string) ($item['name'] ?? '') !== WhatsAppCampusVisitFlowJson::FLOW_NAME) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? ''));

            if ($id !== '') {
                return $id;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    protected function flowIdNamed(array $items, string $name): ?string
    {
        foreach ($items as $item) {
            if ((string) ($item['name'] ?? '') !== $name) {
                continue;
            }

            $id = trim((string) ($item['id'] ?? ''));

            if ($id !== '') {
                return $id;
            }
        }

        return null;
    }

    /**
     * @return array{status: string, flow_id?: string, error?: string}
     */
    protected function createNamedDraft(string $name): array
    {
        try {
            $response = Http::timeout(30)
                ->withToken((string) $this->meta->accessToken())
                ->acceptJson()
                ->post($this->meta->graphUrl($this->meta->wabaId().'/flows'), [
                    'name' => $name,
                    'categories' => ['OTHER'],
                ]);

            $data = $response->json();

            if (! $response->successful() || ! is_array($data)) {
                return [
                    'status' => 'failed',
                    'error' => $this->meta->parseApiError($data, $response->body()),
                ];
            }

            $flowId = trim((string) ($data['id'] ?? ''));

            if ($flowId === '') {
                return ['status' => 'failed', 'error' => 'WhatsApp did not return a form id.'];
            }

            return ['status' => 'success', 'flow_id' => $flowId];
        } catch (\Throwable $exception) {
            Log::error('WhatsApp in-chat form create failed', ['error' => $exception->getMessage()]);

            return ['status' => 'failed', 'error' => $exception->getMessage()];
        }
    }

    /**
     * @return array{status: string, flow_id?: string, error?: string}
     */
    protected function createCampusVisitDraft(): array
    {
        try {
            $response = Http::timeout(30)
                ->withToken((string) $this->meta->accessToken())
                ->acceptJson()
                ->post($this->meta->graphUrl($this->meta->wabaId().'/flows'), [
                    'name' => WhatsAppCampusVisitFlowJson::FLOW_NAME,
                    'categories' => ['APPOINTMENT_BOOKING'],
                ]);

            $data = $response->json();

            if (! $response->successful() || ! is_array($data)) {
                return [
                    'status' => 'failed',
                    'error' => $this->meta->parseApiError($data, $response->body()),
                ];
            }

            $flowId = trim((string) ($data['id'] ?? ''));

            if ($flowId === '') {
                return ['status' => 'failed', 'error' => 'WhatsApp did not return a form id.'];
            }

            return ['status' => 'success', 'flow_id' => $flowId];
        } catch (\Throwable $exception) {
            Log::error('WhatsApp visit form create failed', ['error' => $exception->getMessage()]);

            return ['status' => 'failed', 'error' => $exception->getMessage()];
        }
    }

    /**
     * @return array{status: string, error?: string}
     */
    protected function uploadFlowJson(string $flowId, ?string $json = null): array
    {
        try {
            $response = Http::timeout(60)
                ->withToken((string) $this->meta->accessToken())
                ->attach('file', $json ?? WhatsAppCampusVisitFlowJson::encoded(), 'flow.json')
                ->post($this->meta->graphUrl($flowId.'/assets'), [
                    'name' => 'flow.json',
                    'asset_type' => 'FLOW_JSON',
                ]);

            $data = $response->json();

            if (! $response->successful() || ! is_array($data)) {
                return [
                    'status' => 'failed',
                    'error' => $this->meta->parseApiError($data, $response->body()),
                ];
            }

            $errors = collect($data['validation_errors'] ?? [])
                ->map(function (mixed $error): string {
                    if (! is_array($error)) {
                        return trim((string) $error);
                    }

                    return trim((string) ($error['message'] ?? $error['error'] ?? ''));
                })
                ->filter(fn (string $message): bool => $message !== '')
                ->values()
                ->all();

            if ($errors !== []) {
                return [
                    'status' => 'failed',
                    'error' => 'WhatsApp rejected the form. '.implode(' ', $errors),
                ];
            }

            return ['status' => 'success'];
        } catch (\Throwable $exception) {
            Log::error('WhatsApp visit form upload failed', ['error' => $exception->getMessage()]);

            return ['status' => 'failed', 'error' => $exception->getMessage()];
        }
    }

    /**
     * @return array{status: string, error?: string}
     */
    protected function publishFlow(string $flowId): array
    {
        try {
            $response = Http::timeout(30)
                ->withToken((string) $this->meta->accessToken())
                ->acceptJson()
                ->post($this->meta->graphUrl($flowId.'/publish'));

            $data = $response->json();

            if (! $response->successful()) {
                return [
                    'status' => 'failed',
                    'error' => $this->meta->parseApiError($data, $response->body()),
                ];
            }

            return ['status' => 'success'];
        } catch (\Throwable $exception) {
            Log::error('WhatsApp visit form publish failed', ['error' => $exception->getMessage()]);

            return ['status' => 'failed', 'error' => $exception->getMessage()];
        }
    }

    /**
     * @return array{status: string, error?: string, message_id?: string, response?: mixed}
     */
    protected function messageResult(Response $response): array
    {
        $data = $response->json();

        if ($response->successful() && is_array($data)) {
            $messageId = data_get($data, 'messages.0.id');

            return [
                'status' => 'success',
                'response' => $data,
                'message_id' => is_string($messageId) ? $messageId : null,
            ];
        }

        return [
            'status' => 'failed',
            'error' => $this->meta->parseApiError($data, $response->body()),
            'response' => is_array($data) ? $data : null,
        ];
    }

    /**
     * @return array{status: string, error: string}|null
     */
    protected function managementReady(): ?array
    {
        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            return ['status' => 'failed', 'error' => 'WhatsApp module is not enabled.'];
        }

        if (blank($this->meta->wabaId()) || blank($this->meta->accessToken())) {
            return ['status' => 'failed', 'error' => 'WhatsApp account id or token is missing. Open Connection & Setup.'];
        }

        return null;
    }
}
