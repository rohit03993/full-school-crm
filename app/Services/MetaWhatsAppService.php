<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\MetaWhatsAppTemplate;
use App\Enums\MetaWhatsAppMessageStatus;
use App\Support\CrmNavigation;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsAppService
{
    public function __construct(
        protected MetaWhatsAppMessageLogger $logger,
    ) {}

    /**
     * @param  list<string>  $bodyParams
     * @return array{status: string, response?: mixed, error?: string, message_id?: string}
     */
    public function sendTemplate(
        string $phone,
        string $templateName,
        array $bodyParams = [],
        string $languageCode = 'en',
        int $expectedParamCount = 0,
        array $logContext = [],
    ): array {
        if (! $this->isConfigured()) {
            return ['status' => 'failed', 'error' => 'WhatsApp is not configured. Open '.CrmNavigation::whatsAppMenu('Connection & Setup').' and save credentials.'];
        }

        $destination = $this->destinationE164($phone);

        if (! $destination) {
            return ['status' => 'failed', 'error' => 'Invalid Indian mobile number.'];
        }

        if (blank($templateName)) {
            return ['status' => 'failed', 'error' => 'Template name is required.'];
        }

        $bodyParams = self::normalizeBodyParams($bodyParams, $expectedParamCount);
        $parameterNames = $this->resolveNamedBodyParameterNames($templateName, $languageCode);
        $components = $this->buildBodyComponents($bodyParams, $parameterNames);

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $destination,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
            ],
        ];

        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        $url = $this->graphUrl($this->phoneNumberId().'/messages');

        Log::info('Meta WhatsApp template request', [
            'url' => $url,
            'template' => $templateName,
            'destination' => $destination,
            'param_count' => count($bodyParams),
        ]);

        try {
            $response = Http::timeout(30)
                ->withToken((string) $this->accessToken())
                ->acceptJson()
                ->post($url, $payload);

            $data = $response->json();

            Log::info('Meta WhatsApp template response', [
                'http_status' => $response->status(),
                'body' => $data,
            ]);

            if ($response->successful() && is_array($data)) {
                $messageId = data_get($data, 'messages.0.id');

                $this->logger->recordOutbound(
                    $phone,
                    is_string($messageId) ? $messageId : null,
                    $templateName,
                    $languageCode,
                    $bodyParams,
                    MetaWhatsAppMessageStatus::Sent,
                    null,
                    is_array($data) ? $data : null,
                    $logContext['student_id'] ?? null,
                    null,
                    $logContext,
                );

                return [
                    'status' => 'success',
                    'response' => $data,
                    'message_id' => is_string($messageId) ? $messageId : null,
                ];
            }

            $error = $this->parseApiError($data, $response->body());

            $this->logger->recordOutbound(
                $phone,
                null,
                $templateName,
                $languageCode,
                $bodyParams,
                MetaWhatsAppMessageStatus::Failed,
                $error,
                is_array($data) ? $data : null,
                $logContext['student_id'] ?? null,
                null,
                $logContext,
            );

            return [
                'status' => 'failed',
                'error' => $error,
                'response' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('Meta WhatsApp exception', ['error' => $e->getMessage()]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  list<string>  $bodyParams
     * @param  list<string>  $parameterNames  Named Meta template variables (e.g. student_name). Empty = positional {{1}} {{2}}.
     * @return list<array<string, mixed>>
     */
    public function buildBodyComponents(array $bodyParams, array $parameterNames = []): array
    {
        if ($bodyParams === []) {
            return [];
        }

        $parameters = [];

        foreach (array_values($bodyParams) as $index => $value) {
            $entry = [
                'type' => 'text',
                'text' => $value,
            ];

            $name = trim((string) ($parameterNames[$index] ?? ''));

            if ($name !== '' && ! preg_match('/^\d+$/', $name)) {
                $entry['parameter_name'] = $name;
            }

            $parameters[] = $entry;
        }

        return [
            [
                'type' => 'body',
                'parameters' => $parameters,
            ],
        ];
    }

    /**
     * Meta named-parameter templates require `parameter_name` on each body value.
     * Positional templates ({{1}}, {{2}}) leave this empty.
     *
     * @return list<string>
     */
    public function resolveNamedBodyParameterNames(string $templateName, string $languageCode): array
    {
        $template = MetaWhatsAppTemplate::query()
            ->where('name', $templateName)
            ->where('language', $languageCode)
            ->first();

        if ($template === null) {
            $template = MetaWhatsAppTemplate::query()
                ->where('name', $templateName)
                ->orderByDesc('synced_at')
                ->first();
        }

        $variables = data_get($template?->provider_meta, 'body_variables', []);

        if (! is_array($variables) || $variables === []) {
            return [];
        }

        $names = [];

        foreach ($variables as $variable) {
            $name = trim((string) $variable);

            if ($name === '' || preg_match('/^\d+$/', $name) === 1) {
                return [];
            }

            $names[] = $name;
        }

        return $names;
    }

    /**
     * @param  list<string>  $bodyParams
     * @return list<string>
     */
    public static function normalizeBodyParams(array $bodyParams, int $expectedParamCount = 0): array
    {
        $params = array_values(array_map(
            fn (mixed $value): string => trim((string) $value),
            $bodyParams,
        ));

        if ($expectedParamCount > 0) {
            $params = array_slice($params, 0, $expectedParamCount);
            $params = array_pad($params, $expectedParamCount, '');
        }

        return array_map(
            fn (string $value): string => $value === '' ? '—' : $value,
            $params,
        );
    }

    /**
     * @return array{status: string, message: string, display_phone_number?: string, verified_name?: string}
     */
    public function validateConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['status' => 'failed', 'message' => 'Phone number ID and access token are required.'];
        }

        try {
            $response = Http::timeout(30)
                ->withToken((string) $this->accessToken())
                ->acceptJson()
                ->get($this->graphUrl($this->phoneNumberId()), [
                    'fields' => 'display_phone_number,verified_name,quality_rating',
                ]);

            $data = $response->json();

            if (! $response->successful() || ! is_array($data)) {
                return [
                    'status' => 'failed',
                    'message' => $this->parseApiError($data, $response->body()),
                ];
            }

            $display = (string) ($data['display_phone_number'] ?? '');
            $verified = (string) ($data['verified_name'] ?? '');

            $message = trim('Connected to Meta.'.($display !== '' ? " Number: {$display}." : '').($verified !== '' ? " Business: {$verified}." : ''));

            return [
                'status' => 'success',
                'message' => $message !== '' ? $message : 'Connected to Meta.',
                'display_phone_number' => $display !== '' ? $display : null,
                'verified_name' => $verified !== '' ? $verified : null,
            ];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'message' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{status: string, data?: array<string, mixed>, error?: string}
     */
    public function createMessageTemplate(array $body): array
    {
        if (! filled($this->accessToken())) {
            return ['status' => 'failed', 'error' => 'Access token is not configured.'];
        }

        if (blank($this->wabaId())) {
            return ['status' => 'failed', 'error' => 'WhatsApp Business Account ID (WABA) is required to create templates.'];
        }

        try {
            $response = Http::timeout(60)
                ->withToken((string) $this->accessToken())
                ->acceptJson()
                ->post($this->graphUrl($this->wabaId().'/message_templates'), $body);

            $data = $response->json();

            if ($response->successful() && is_array($data)) {
                return ['status' => 'success', 'data' => $data];
            }

            return [
                'status' => 'failed',
                'error' => $this->parseApiError($data, $response->body()),
            ];
        } catch (\Throwable $e) {
            Log::error('Meta WhatsApp template create failed', ['error' => $e->getMessage()]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: string, items?: list<array<string, mixed>>, error?: string}
     */
    public function fetchTemplates(): array
    {
        if (! filled($this->accessToken())) {
            return ['status' => 'failed', 'error' => 'Access token is not configured.'];
        }

        if (blank($this->wabaId())) {
            return ['status' => 'failed', 'error' => 'WhatsApp Business Account ID (WABA) is required to sync templates.'];
        }

        try {
            $response = Http::timeout(30)
                ->withToken((string) $this->accessToken())
                ->acceptJson()
                ->get($this->graphUrl($this->wabaId().'/message_templates'), [
                    'limit' => 100,
                ]);

            $data = $response->json();

            if (! $response->successful() || ! is_array($data)) {
                return [
                    'status' => 'failed',
                    'error' => $this->parseApiError($data, $response->body()),
                ];
            }

            $items = collect($data['data'] ?? [])
                ->filter(fn (mixed $item): bool => is_array($item))
                ->values()
                ->all();

            return ['status' => 'success', 'items' => $items];
        } catch (\Throwable $e) {
            Log::error('Meta WhatsApp template fetch failed', ['error' => $e->getMessage()]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function isConfigured(): bool
    {
        return filled($this->phoneNumberId()) && filled($this->accessToken());
    }

    public function hasStoredAccessToken(): bool
    {
        return filled(Setting::getValue('meta_whatsapp.access_token'));
    }

    public function maskedAccessToken(): ?string
    {
        $token = $this->accessToken();

        if (blank($token)) {
            return null;
        }

        $token = trim((string) $token);

        if (strlen($token) <= 12) {
            return str_repeat('•', min(strlen($token), 8));
        }

        return substr($token, 0, 8).'…'.substr($token, -4);
    }

    public function phoneNumberId(): ?string
    {
        $fromSettings = Setting::getValue('meta_whatsapp.phone_number_id');

        if (filled($fromSettings)) {
            return trim((string) $fromSettings);
        }

        $fromEnv = config('meta_whatsapp.phone_number_id');

        return filled($fromEnv) ? trim((string) $fromEnv) : null;
    }

    public function wabaId(): ?string
    {
        $fromSettings = Setting::getValue('meta_whatsapp.waba_id');

        if (filled($fromSettings)) {
            return trim((string) $fromSettings);
        }

        $fromEnv = config('meta_whatsapp.waba_id');

        return filled($fromEnv) ? trim((string) $fromEnv) : null;
    }

    public function defaultLanguage(): string
    {
        $fromSettings = Setting::getValue('meta_whatsapp.default_language');

        if (filled($fromSettings)) {
            return trim((string) $fromSettings);
        }

        return (string) config('meta_whatsapp.default_language', 'en');
    }

    public function verifyToken(): ?string
    {
        $fromSettings = Setting::getValue('meta_whatsapp.verify_token');

        if (filled($fromSettings)) {
            return trim((string) $fromSettings);
        }

        $fromEnv = config('meta_whatsapp.verify_token');

        return filled($fromEnv) ? trim((string) $fromEnv) : null;
    }

    public function appSecret(): ?string
    {
        $fromSettings = Setting::getValue('meta_whatsapp.app_secret');

        if (filled($fromSettings)) {
            return $this->decryptSecret((string) $fromSettings);
        }

        $fromEnv = config('meta_whatsapp.app_secret');

        return filled($fromEnv) ? trim((string) $fromEnv) : null;
    }

    public function webhookUrl(): string
    {
        return url('/webhooks/meta/whatsapp');
    }

    public function accessToken(): ?string
    {
        $fromSettings = Setting::getValue('meta_whatsapp.access_token');

        if (filled($fromSettings)) {
            return $this->decryptSecret((string) $fromSettings);
        }

        $fromEnv = config('meta_whatsapp.access_token');

        return filled($fromEnv) ? trim((string) $fromEnv) : null;
    }

    /**
     * Meta expects digits only with country code (no plus).
     */
    public function destinationE164(string $phone): ?string
    {
        if ($phone === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 10) {
            return '91'.$digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            return $digits;
        }

        return null;
    }

    public function graphUrl(string $path): string
    {
        $version = trim((string) config('meta_whatsapp.graph_version', 'v20.0'), '/');
        $path = ltrim($path, '/');

        return 'https://graph.facebook.com/'.$version.'/'.$path;
    }

    protected function decryptSecret(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (\Throwable) {
            return $value;
        }
    }

    /**
     * @param  mixed  $data
     */
    public function parseApiError(mixed $data, string $fallbackBody): string
    {
        if (! is_array($data)) {
            return $fallbackBody !== '' ? $fallbackBody : 'Unknown Meta API error';
        }

        $error = $data['error'] ?? null;

        if (is_array($error)) {
            $message = (string) ($error['message'] ?? '');
            $code = $error['code'] ?? null;
            $subcode = $error['error_subcode'] ?? null;

            $parts = array_filter([
                $message !== '' ? $message : null,
                $code !== null ? '(code '.$code.')' : null,
                $subcode !== null ? '(subcode '.$subcode.')' : null,
            ]);

            if ($parts !== []) {
                return implode(' ', $parts);
            }
        }

        if (filled($data['message'] ?? null)) {
            return (string) $data['message'];
        }

        return $fallbackBody !== '' ? $fallbackBody : 'Unknown Meta API error';
    }

    /**
     * Send a login/authentication OTP using an approved Meta template.
     * Optionally includes a button parameter (copy-code / URL button) when enabled in settings.
     *
     * @return array{status: string, response?: mixed, error?: string, message_id?: string}
     */
    public function sendAuthenticationOtp(
        string $phone,
        string $otp,
        string $templateName,
        ?string $languageCode = null,
        ?string $contactName = null,
    ): array {
        if (! $this->isConfigured()) {
            return ['status' => 'failed', 'error' => 'WhatsApp is not configured. Open '.CrmNavigation::whatsAppMenu('Connection & Setup').' and save credentials.'];
        }

        $destination = $this->destinationE164($phone);

        if (! $destination) {
            return ['status' => 'failed', 'error' => 'Invalid Indian mobile number.'];
        }

        $otp = trim($otp);
        $templateName = trim($templateName);

        if ($templateName === '') {
            return ['status' => 'failed', 'error' => 'OTP template name is not set. Save it under WhatsApp setup.'];
        }

        if (! preg_match('/^\d{4,8}$/', $otp)) {
            return ['status' => 'failed', 'error' => 'Invalid OTP format.'];
        }

        $languageCode = trim((string) ($languageCode ?: $this->defaultLanguage())) ?: 'en';
        $includeButton = filter_var(
            Setting::getValue('meta_whatsapp.otp_include_button_param', config('meta_whatsapp.otp_include_button_param', true)),
            FILTER_VALIDATE_BOOLEAN,
        );

        $components = $this->buildBodyComponents([$otp]);

        if ($includeButton) {
            $components[] = [
                'type' => 'button',
                'sub_type' => 'url',
                'index' => '0',
                'parameters' => [
                    ['type' => 'text', 'text' => $otp],
                ],
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $destination,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $languageCode],
                'components' => $components,
            ],
        ];

        $url = $this->graphUrl($this->phoneNumberId().'/messages');

        Log::info('Meta WhatsApp OTP template request', [
            'url' => $url,
            'template' => $templateName,
            'destination' => $destination,
            'contact' => $contactName,
        ]);

        try {
            $response = Http::timeout(30)
                ->withToken((string) $this->accessToken())
                ->acceptJson()
                ->post($url, $payload);

            $data = $response->json();

            Log::info('Meta WhatsApp OTP template response', [
                'http_status' => $response->status(),
                'body' => $data,
            ]);

            if ($response->successful() && is_array($data)) {
                $messageId = data_get($data, 'messages.0.id');

                $this->logger->recordOutbound(
                    $phone,
                    is_string($messageId) ? $messageId : null,
                    $templateName,
                    $languageCode,
                    [$otp],
                    MetaWhatsAppMessageStatus::Sent,
                    null,
                    $data,
                    null,
                    'OTP login code',
                    [
                        'message_source' => \App\Enums\WhatsAppMessageSource::Automation->value,
                        'contact_name' => $contactName,
                    ],
                );

                return [
                    'status' => 'success',
                    'response' => $data,
                    'message_id' => is_string($messageId) ? $messageId : null,
                ];
            }

            $error = $this->parseApiError($data, $response->body());

            $this->logger->recordOutbound(
                $phone,
                null,
                $templateName,
                $languageCode,
                [$otp],
                MetaWhatsAppMessageStatus::Failed,
                $error,
                is_array($data) ? $data : null,
                null,
                'OTP login code',
                [
                    'message_source' => \App\Enums\WhatsAppMessageSource::Automation->value,
                ],
            );

            return [
                'status' => 'failed',
                'error' => $error,
                'response' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('Meta WhatsApp OTP send failed', [
                'message' => $e->getMessage(),
            ]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: string, response?: mixed, error?: string, message_id?: string}
     */
    public function sendText(string $phone, string $text): array
    {
        if (! $this->isConfigured()) {
            return ['status' => 'failed', 'error' => 'WhatsApp is not configured. Open '.CrmNavigation::whatsAppMenu('Connection & Setup').' and save credentials.'];
        }

        $destination = $this->destinationE164($phone);

        if (! $destination) {
            return ['status' => 'failed', 'error' => 'Invalid Indian mobile number.'];
        }

        $text = trim($text);

        if ($text === '') {
            return ['status' => 'failed', 'error' => 'Message text is required.'];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $destination,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => mb_substr($text, 0, 4096),
            ],
        ];

        $url = $this->graphUrl($this->phoneNumberId().'/messages');

        try {
            $response = Http::timeout(30)
                ->withToken((string) $this->accessToken())
                ->acceptJson()
                ->post($url, $payload);

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
                'error' => $this->parseApiError($data, $response->body()),
                'response' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('Meta WhatsApp text exception', ['error' => $e->getMessage()]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * @return array{status: string, response?: mixed, error?: string, message_id?: string}
     */
    public function sendMedia(
        string $phone,
        string $messageType,
        string $mediaId,
        ?string $caption = null,
        ?string $filename = null,
    ): array {
        if (! $this->isConfigured()) {
            return ['status' => 'failed', 'error' => 'WhatsApp is not configured. Open '.CrmNavigation::whatsAppMenu('Connection & Setup').' and save credentials.'];
        }

        $destination = $this->destinationE164($phone);

        if (! $destination) {
            return ['status' => 'failed', 'error' => 'Invalid Indian mobile number.'];
        }

        $messageType = match ($messageType) {
            'image', 'video', 'audio', 'document' => $messageType,
            default => 'document',
        };

        $mediaPayload = ['id' => $mediaId];

        if ($caption !== null && $caption !== '' && in_array($messageType, ['image', 'video', 'document'], true)) {
            $mediaPayload['caption'] = mb_substr($caption, 0, 1024);
        }

        if ($filename !== null && $filename !== '' && $messageType === 'document') {
            $mediaPayload['filename'] = mb_substr($filename, 0, 240);
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $destination,
            'type' => $messageType,
            $messageType => $mediaPayload,
        ];

        $url = $this->graphUrl($this->phoneNumberId().'/messages');

        try {
            $response = Http::timeout(60)
                ->withToken((string) $this->accessToken())
                ->acceptJson()
                ->post($url, $payload);

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
                'error' => $this->parseApiError($data, $response->body()),
                'response' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('Meta WhatsApp media send exception', ['error' => $e->getMessage()]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }
}
