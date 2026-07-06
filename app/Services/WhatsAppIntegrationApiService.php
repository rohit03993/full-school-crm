<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class WhatsAppIntegrationApiService
{
    public function hasStoredKey(): bool
    {
        return filled(Setting::getValue('whatsapp.integration_api_key'));
    }

    public function maskedKey(): ?string
    {
        $key = $this->storedKey();

        if (blank($key)) {
            return null;
        }

        if (strlen($key) <= 16) {
            return str_repeat('•', 8);
        }

        return substr($key, 0, 12).'…'.substr($key, -4);
    }

    /**
     * Generate a new integration key and replace any existing one.
     */
    public function generateKey(): string
    {
        $id = (string) Str::uuid();
        $secret = Str::random(40);
        $key = 'crm.'.$id.'.'.$secret;

        Setting::setValue('whatsapp.integration_api_key', Crypt::encryptString($key), 'whatsapp');
        Setting::setValue('whatsapp.integration_api_key_id', $id, 'whatsapp');

        return $key;
    }

    public function validateKey(?string $apiKey): bool
    {
        $apiKey = trim((string) $apiKey);
        $stored = $this->storedKey();

        return filled($stored) && filled($apiKey) && hash_equals($stored, $apiKey);
    }

    public function apiEndpointUrl(): string
    {
        return url('/api/v1/campaign/t1/api/v2');
    }

    public function storedKey(): ?string
    {
        $encrypted = Setting::getValue('whatsapp.integration_api_key');

        if (blank($encrypted)) {
            $fromEnv = config('whatsapp.integration_api_key');

            return filled($fromEnv) ? trim((string) $fromEnv) : null;
        }

        try {
            return Crypt::decryptString((string) $encrypted);
        } catch (\Throwable) {
            return trim((string) $encrypted);
        }
    }
}
