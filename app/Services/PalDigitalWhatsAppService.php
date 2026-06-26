<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PalDigitalWhatsAppService
{
    /**
     * @param  list<string>  $templateParams
     * @return array{status: string, response?: mixed, error?: string}
     */
    public function send(
        string $phone,
        array $templateParams = [],
        ?string $templateName = null,
        ?string $userName = null,
        int $expectedParamCount = 0,
    ): array {
        if (! $this->isConfigured()) {
            return ['status' => 'failed', 'error' => 'Pal Digital API key or URL not configured.'];
        }

        $key = (string) $this->apiKey();

        $destination = $this->destinationForProvider($phone);

        if (! $destination) {
            return ['status' => 'failed', 'error' => 'Invalid Indian mobile number.'];
        }

        $campaignName = $templateName ?? config('services.pal_digital.default_template');

        if (blank($campaignName)) {
            return ['status' => 'failed', 'error' => 'WhatsApp template name is required.'];
        }

        $templateParams = self::normalizeTemplateParams($templateParams, $expectedParamCount);

        $userName = $this->sanitizeUserName($userName ?? $templateParams[0] ?? 'User');

        $payload = [
            'apiKey' => $key,
            'campaignName' => $campaignName,
            'destination' => $destination,
            'userName' => $userName,
            'templateParams' => $templateParams,
        ];

        Log::info('Pal Digital WhatsApp request', [
            'url' => $this->apiUrl(),
            'campaignName' => $campaignName,
            'destination' => $destination,
            'param_count' => count($templateParams),
            'templateParams' => $templateParams,
        ]);

        try {
            $response = Http::timeout(30)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($this->apiUrl(), $payload);

            $data = $response->json();

            Log::info('Pal Digital WhatsApp response', [
                'http_status' => $response->status(),
                'body' => $data,
            ]);

            if ($response->successful() && $this->responseIndicatesSuccess($data)) {
                return ['status' => 'success', 'response' => $data];
            }

            return [
                'status' => 'failed',
                'error' => $this->parseApiError($data, $response->body()),
                'response' => $data,
            ];
        } catch (\Throwable $e) {
            Log::error('Pal Digital WhatsApp exception', ['error' => $e->getMessage()]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    /**
     * Pad to the template slot count and replace blanks so providers do not drop trailing params.
     *
     * @param  list<string>  $templateParams
     * @return list<string>
     */
    public static function normalizeTemplateParams(array $templateParams, int $expectedParamCount = 0): array
    {
        $params = array_values($templateParams);

        if ($expectedParamCount > 0) {
            $params = array_slice($params, 0, $expectedParamCount);
            $params = array_pad($params, $expectedParamCount, '');
        }

        return array_map(
            fn (string $value): string => trim($value) === '' ? '—' : $value,
            $params,
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->apiKey()) && filled($this->apiUrl());
    }

    public function hasStoredApiKey(): bool
    {
        return filled(Setting::getValue('pal_digital.api_key'));
    }

    public function maskedApiKey(): ?string
    {
        $key = $this->apiKey();

        if (blank($key)) {
            return null;
        }

        $key = trim((string) $key);

        if (strlen($key) <= 12) {
            return str_repeat('•', min(strlen($key), 8));
        }

        return substr($key, 0, 8).'…'.substr($key, -4);
    }

    public function hasValidIntegrationKey(): bool
    {
        return $this->isWaserviceIntegrationKey();
    }

    public function isWaserviceIntegrationKey(?string $key = null): bool
    {
        $key ??= $this->apiKey();

        return filled($key) && str_starts_with(trim((string) $key), 'wsk.');
    }

    /**
     * @return array{status: string, message: string}
     */
    public function validateConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['status' => 'failed', 'message' => 'API key and URL are required.'];
        }

        if (! $this->isWaserviceIntegrationKey()) {
            return [
                'status' => 'failed',
                'message' => 'Use a waservice integration key (wsk....) from Pal Digital → Integrations — not an old AiSensy JWT or login password.',
            ];
        }

        $result = $this->fetchApiCampaigns();

        if ($result['status'] !== 'success') {
            return [
                'status' => 'failed',
                'message' => (string) ($result['error'] ?? 'Could not connect to waservice.'),
            ];
        }

        $count = count($result['items'] ?? []);

        return [
            'status' => 'success',
            'message' => "Connected. {$count} live API campaign(s) found.",
        ];
    }

    /**
     * @return array{status: string, items?: list<array<string, mixed>>, error?: string}
     */
    public function fetchApiCampaigns(): array
    {
        return $this->fetchIntegrationList('api-campaigns', ['status' => 'live']);
    }

    /**
     * @return array{status: string, items?: list<array<string, mixed>>, error?: string}
     */
    public function fetchTemplates(): array
    {
        return $this->fetchIntegrationList('templates', ['status' => 'APPROVED']);
    }

    /**
     * @param  array<string, string>  $query
     * @return array{status: string, items?: list<array<string, mixed>>, error?: string}
     */
    protected function fetchIntegrationList(string $path, array $query = []): array
    {
        if (! filled($this->apiKey())) {
            return ['status' => 'failed', 'error' => 'API key is not configured.'];
        }

        $url = $this->integrationUrl($path);

        if (blank($url)) {
            return ['status' => 'failed', 'error' => 'Could not build waservice integration URL.'];
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['X-Integration-Key' => (string) $this->apiKey()])
                ->get($url, $query);

            $data = $response->json();

            if (! $response->successful()) {
                return [
                    'status' => 'failed',
                    'error' => $this->parseApiError($data, $response->body()),
                ];
            }

            if (! is_array($data)) {
                return ['status' => 'failed', 'error' => 'Unexpected response from waservice.'];
            }

            return ['status' => 'success', 'items' => $data];
        } catch (\Throwable $e) {
            Log::error('Pal Digital integration fetch failed', ['path' => $path, 'error' => $e->getMessage()]);

            return ['status' => 'failed', 'error' => $e->getMessage()];
        }
    }

    public function integrationUrl(string $path): ?string
    {
        $base = $this->apiBaseUrl();

        if (blank($base)) {
            return null;
        }

        $prefix = rtrim((string) config('services.pal_digital.api_v1_prefix', '/api/v1'), '/');

        return rtrim($base, '/').$prefix.'/integrations/'.ltrim($path, '/');
    }

    public function apiKey(): ?string
    {
        $fromSettings = Setting::getValue('pal_digital.api_key');

        if (filled($fromSettings)) {
            return trim((string) $fromSettings);
        }

        $fromEnv = config('services.pal_digital.api_key');

        return filled($fromEnv) ? trim((string) $fromEnv) : null;
    }

    public function apiUrl(): ?string
    {
        $fromSettings = Setting::getValue('pal_digital.api_url');

        $url = filled($fromSettings)
            ? (string) $fromSettings
            : config('services.pal_digital.api_url');

        if (blank($url)) {
            return null;
        }

        return $this->normalizeUrl(trim($url));
    }

    public function apiBaseUrl(): ?string
    {
        $url = $this->apiUrl();

        if (blank($url)) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port;
    }

    /**
     * waservice accepts 91XXXXXXXXXX or +91…; docs use digits without plus.
     */
    public function destinationForProvider(string $phone): ?string
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

    protected function sanitizeUserName(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9 ]+/', '', $name) ?? 'User';
        $clean = trim($clean);

        return $clean === '' ? 'User' : mb_substr($clean, 0, 120);
    }

    protected function normalizeUrl(string $url): string
    {
        $url = rtrim($url, '/');
        $path = parse_url($url, PHP_URL_PATH) ?? '';

        if ($path === '' || $path === '/') {
            return $url.config('services.pal_digital.api_v1_prefix', '/api/v1').'/campaign/t1/api/v2';
        }

        if (preg_match('#/api/v1/?$#', $path)) {
            return $url.'/campaign/t1/api/v2';
        }

        if (! str_contains($path, 'campaign/t1/api/v2')) {
            return $url.'/campaign/t1/api/v2';
        }

        return $url;
    }

    /**
     * @param  mixed  $data
     */
    protected function responseIndicatesSuccess(mixed $data): bool
    {
        if (! is_array($data)) {
            return true;
        }

        if (array_key_exists('success', $data)) {
            return (bool) $data['success'];
        }

        return true;
    }

    /**
     * @param  mixed  $data
     */
    protected function parseApiError(mixed $data, string $fallbackBody): string
    {
        if (! is_array($data)) {
            return $fallbackBody !== '' ? $fallbackBody : 'Unknown error';
        }

        if (filled($data['message'] ?? null)) {
            return (string) $data['message'];
        }

        $detail = $data['detail'] ?? null;

        if (is_string($detail) && $detail !== '') {
            return $detail;
        }

        if (is_array($detail)) {
            $messages = collect($detail)
                ->map(function (mixed $item): ?string {
                    if (is_string($item)) {
                        return $item;
                    }

                    if (is_array($item) && filled($item['msg'] ?? null)) {
                        return (string) $item['msg'];
                    }

                    return null;
                })
                ->filter()
                ->values()
                ->all();

            if ($messages !== []) {
                return implode(' ', $messages);
            }
        }

        return $fallbackBody !== '' ? $fallbackBody : 'Unknown error';
    }
}
