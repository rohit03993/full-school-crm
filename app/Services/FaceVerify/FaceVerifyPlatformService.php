<?php

namespace App\Services\FaceVerify;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

class FaceVerifyPlatformService
{
    public const SETTING_GROUP = 'face_verify';

    /**
     * Overlay DB-connected Face Platform credentials onto config('face_verify.*').
     */
    public function applyConfiguredOverrides(): void
    {
        if (! $this->settingsTableReady()) {
            return;
        }

        $map = [
            'api_url' => 'face_verify.api_url',
            'service_token' => 'face_verify.service_token',
            'callback_secret' => 'face_verify.callback_secret',
            'default_device_id' => 'face_verify.default_device_id',
        ];

        foreach ($map as $configKey => $settingKey) {
            $value = Setting::getValue($settingKey);
            if (filled($value)) {
                config(["face_verify.{$configKey}" => $value]);
            }
        }

        $enabled = Setting::getValue('face_verify.enabled');
        if ($enabled !== null && $enabled !== '') {
            config(['face_verify.enabled' => filter_var($enabled, FILTER_VALIDATE_BOOLEAN)]);
        }
    }

    /**
     * @return array{
     *     connected: bool,
     *     api_url: ?string,
     *     client_code: ?string,
     *     tenant_id: ?string,
     *     tenant_name: ?string,
     *     devices: list<array{id: string, name: string}>,
     *     default_device_id: ?string,
     * }
     */
    public function status(): array
    {
        $this->applyConfiguredOverrides();

        $devices = Setting::getValue('face_verify.devices', []);
        if (! is_array($devices)) {
            $devices = [];
        }

        return [
            'connected' => filled(Setting::getValue('face_verify.tenant_id'))
                && filled(config('face_verify.api_url'))
                && filled(config('face_verify.service_token')),
            'api_url' => config('face_verify.api_url') ? (string) config('face_verify.api_url') : null,
            'client_code' => Setting::getValue('face_verify.client_code'),
            'tenant_id' => Setting::getValue('face_verify.tenant_id'),
            'tenant_name' => Setting::getValue('face_verify.tenant_name'),
            'devices' => array_values(array_map(
                fn ($row): array => [
                    'id' => (string) ($row['id'] ?? ''),
                    'name' => (string) ($row['name'] ?? 'Device'),
                ],
                $devices,
            )),
            'default_device_id' => config('face_verify.default_device_id')
                ? (string) config('face_verify.default_device_id')
                : null,
        ];
    }

    /**
     * @return array{ok: bool, message: string, status: array<string, mixed>}
     */
    public function connect(string $faceApiUrl, string $clientCode): array
    {
        $faceApiUrl = rtrim(trim($faceApiUrl), '/');
        $clientCode = strtoupper(trim($clientCode));

        if ($faceApiUrl === '' || $clientCode === '') {
            throw new RuntimeException('Face URL and client code are required.');
        }

        $crmBase = rtrim((string) config('app.url'), '/');
        if ($crmBase === '') {
            throw new RuntimeException('APP_URL is not set on this CRM.');
        }

        $response = Http::timeout(15)
            ->acceptJson()
            ->asJson()
            ->post($faceApiUrl.'/platform/connect', [
                'client_code' => $clientCode,
                'crm_base_url' => $crmBase,
            ]);

        if (! $response->successful()) {
            $detail = $response->json('detail') ?? $response->body();
            throw new RuntimeException('Face Platform connect failed: '.(is_string($detail) ? $detail : json_encode($detail)));
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        if (($data['ok'] ?? true) !== true && ! isset($data['service_token'])) {
            throw new RuntimeException('Face Platform rejected the client code.');
        }

        $devices = [];
        foreach (($data['devices'] ?? []) as $device) {
            if (! is_array($device) || blank($device['id'] ?? null)) {
                continue;
            }
            $devices[] = [
                'id' => (string) $device['id'],
                'name' => (string) ($device['name'] ?? 'Gate'),
            ];
        }

        Setting::setValue('face_verify.api_url', $faceApiUrl, self::SETTING_GROUP);
        Setting::setValue('face_verify.client_code', (string) ($data['client_code'] ?? $clientCode), self::SETTING_GROUP);
        Setting::setValue('face_verify.tenant_id', (string) ($data['tenant_id'] ?? ''), self::SETTING_GROUP);
        Setting::setValue('face_verify.tenant_name', (string) ($data['name'] ?? ''), self::SETTING_GROUP);
        Setting::setValue('face_verify.service_token', (string) ($data['service_token'] ?? ''), self::SETTING_GROUP);
        Setting::setValue('face_verify.callback_secret', (string) ($data['callback_secret'] ?? ''), self::SETTING_GROUP);
        Setting::setValue('face_verify.devices', $devices, self::SETTING_GROUP);
        Setting::setValue('face_verify.enabled', 'true', self::SETTING_GROUP);

        $defaultDevice = $devices[0]['id'] ?? null;
        if (filled($defaultDevice)) {
            Setting::setValue('face_verify.default_device_id', $defaultDevice, self::SETTING_GROUP);
        }

        Setting::flushValueCache();
        $this->applyConfiguredOverrides();

        return [
            'ok' => true,
            'message' => 'Connected to Face Platform. Use the device number(s) below in the APK Settings.',
            'status' => $this->status(),
        ];
    }

    public function disconnect(): void
    {
        foreach ([
            'face_verify.api_url',
            'face_verify.client_code',
            'face_verify.tenant_id',
            'face_verify.tenant_name',
            'face_verify.service_token',
            'face_verify.callback_secret',
            'face_verify.devices',
            'face_verify.default_device_id',
            'face_verify.enabled',
        ] as $key) {
            Setting::setValue($key, '', self::SETTING_GROUP);
        }
        Setting::flushValueCache();
    }

    private function settingsTableReady(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (Throwable) {
            return false;
        }
    }
}
