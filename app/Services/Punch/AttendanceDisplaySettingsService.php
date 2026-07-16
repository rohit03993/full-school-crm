<?php

namespace App\Services\Punch;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class AttendanceDisplaySettingsService
{
    public const KEY_ENABLED = 'attendance.display.enabled';

    public const KEY_TOKEN = 'attendance.display.token';

    public function isEnabled(): bool
    {
        return filter_var(Setting::getValue(self::KEY_ENABLED, false), FILTER_VALIDATE_BOOL);
    }

    public function token(): ?string
    {
        $token = Setting::getValue(self::KEY_TOKEN);

        return filled($token) && is_string($token) ? $token : null;
    }

    public function isValidToken(?string $token): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $stored = $this->token();

        if ($stored === null || ! filled($token)) {
            return false;
        }

        return hash_equals($stored, (string) $token);
    }

    public function displayUrl(): ?string
    {
        $token = $this->token();

        if (! $this->isEnabled() || $token === null) {
            return null;
        }

        return route('display.attendance.show', ['token' => $token]);
    }

    public function enable(bool $enabled = true): void
    {
        Setting::setValue(self::KEY_ENABLED, $enabled ? '1' : '0', 'attendance');

        if ($enabled && $this->token() === null) {
            $this->regenerateToken();
        }
    }

    public function regenerateToken(): string
    {
        $token = Str::random(48);
        Setting::setValue(self::KEY_TOKEN, $token, 'attendance');

        return $token;
    }

    public function tokenFingerprint(): ?string
    {
        $token = $this->token();

        return $token !== null ? hash('sha256', $token) : null;
    }

    public function signedPhotoUrl(int $documentId): ?string
    {
        $fingerprint = $this->tokenFingerprint();

        if ($fingerprint === null || ! $this->isEnabled()) {
            return null;
        }

        $ttlMinutes = max(5, (int) config('attendance_display.photo_url_ttl_minutes', 360));
        $cacheKey = 'attendance_display.photo_url.'.$fingerprint.'.'.$documentId;

        return Cache::remember(
            $cacheKey,
            now()->addMinutes($ttlMinutes),
            fn (): string => URL::temporarySignedRoute(
                'display.attendance.photo',
                now()->addMinutes($ttlMinutes),
                [
                    'document' => $documentId,
                    'display' => $fingerprint,
                ],
            ),
        );
    }
}
