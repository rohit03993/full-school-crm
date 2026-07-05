<?php

namespace App\Services;

use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Models\Setting;
use Carbon\Carbon;

class LicenseService
{
    public const PAYLOAD_KEY = 'license.payload';

    public const SIGNATURE_KEY = 'license.signature';

    /**
     * @return array{
     *     plan: string,
     *     features: list<string>,
     *     expires_at: string,
     *     annual_price_inr: int|null,
     *     client_name: string|null,
     *     notes: string|null,
     *     max_students: int|null,
     *     updated_at: string,
     * }
     */
    public function current(): array
    {
        $this->ensureDefaultLicense();

        $payload = Setting::getValue(self::PAYLOAD_KEY);

        if (! is_array($payload)) {
            return $this->defaultPayload();
        }

        return $payload;
    }

    public function isSignatureValid(): bool
    {
        $this->ensureDefaultLicense();

        return $this->hasValidStoredSignature();
    }

    public function isActive(): bool
    {
        if (! $this->isSignatureValid()) {
            return false;
        }

        return ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        $expiresAt = $this->expiresAt();

        return $expiresAt !== null && $expiresAt->isPast();
    }

    public function expiresAt(): ?Carbon
    {
        $value = $this->current()['expires_at'] ?? null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value);
    }

    public function daysRemaining(): ?int
    {
        $expiresAt = $this->expiresAt();

        if ($expiresAt === null) {
            return null;
        }

        return (int) now()->startOfDay()->diffInDays($expiresAt->startOfDay(), false);
    }

    /**
     * Read-only summary for the school Super Admin dashboard.
     *
     * @return array{
     *     expires_at_label: string|null,
     *     days_remaining: int|null,
     *     level: string,
     *     plan_label: string,
     *     show_warning: bool,
     * }
     */
    public function dashboardSummary(): array
    {
        $expiresAt = $this->expiresAt();
        $daysRemaining = $this->daysRemaining();
        $warningDays = max(1, (int) config('license.expiry_warning_days', 30));
        $criticalDays = max(1, min($warningDays, (int) config('license.expiry_critical_days', 7)));

        $level = 'ok';

        if (! $this->isActive()) {
            $level = 'expired';
        } elseif ($daysRemaining !== null && $daysRemaining <= $criticalDays) {
            $level = 'critical';
        } elseif ($daysRemaining !== null && $daysRemaining <= $warningDays) {
            $level = 'warning';
        }

        return [
            'expires_at_label' => $expiresAt?->format('d M Y'),
            'days_remaining' => $daysRemaining,
            'level' => $level,
            'plan_label' => count($this->sanitizeFeatures(
                is_array($this->current()['features'] ?? null) ? $this->current()['features'] : []
            )).' modules enabled',
            'show_warning' => in_array($level, ['warning', 'critical'], true),
        ];
    }

    public function plan(): LicensePlan
    {
        return LicensePlan::tryFrom((string) ($this->current()['plan'] ?? ''))
            ?? LicensePlan::Custom;
    }

    /**
     * @return list<string>
     */
    public function enabledFeatureKeys(): array
    {
        $this->ensureDefaultLicense();

        if (! $this->isActive()) {
            return [];
        }

        $features = $this->current()['features'] ?? [];

        return is_array($features)
            ? array_values(array_intersect(LicenseFeature::values(), $features))
            : [];
    }

    public function hasFeature(LicenseFeature|string $feature): bool
    {
        $key = $feature instanceof LicenseFeature ? $feature->value : $feature;

        return in_array($key, $this->enabledFeatureKeys(), true);
    }

    /**
     * @param  array{
     *     plan?: string,
     *     features?: list<string>,
     *     expires_at?: string,
     *     annual_price_inr?: int|null,
     *     client_name?: string|null,
     *     notes?: string|null,
     *     max_students?: int|null,
     * }  $data
     */
    public function save(array $data): void
    {
        $plan = LicensePlan::tryFrom((string) ($data['plan'] ?? LicensePlan::Custom->value))
            ?? LicensePlan::Custom;

        $features = $plan === LicensePlan::Custom
            ? $this->sanitizeFeatures($data['features'] ?? [])
            : $this->featuresForPlan($plan);

        $payload = [
            'plan' => $plan->value,
            'features' => $features,
            'expires_at' => Carbon::parse((string) ($data['expires_at'] ?? now()->addYear()))->endOfDay()->toIso8601String(),
            'annual_price_inr' => isset($data['annual_price_inr']) && $data['annual_price_inr'] !== ''
                ? max(0, (int) $data['annual_price_inr'])
                : null,
            'client_name' => filled($data['client_name'] ?? null) ? trim((string) $data['client_name']) : null,
            'notes' => filled($data['notes'] ?? null) ? trim((string) $data['notes']) : null,
            'max_students' => isset($data['max_students']) && $data['max_students'] !== ''
                ? max(0, (int) $data['max_students'])
                : null,
            'updated_at' => now()->toIso8601String(),
        ];

        Setting::setValue(self::PAYLOAD_KEY, $payload, 'license');
        Setting::setValue(self::SIGNATURE_KEY, $this->sign($payload), 'license');
        Setting::flushValueCache();
    }

    public function applyPlan(LicensePlan $plan, ?Carbon $expiresAt = null): void
    {
        $current = $this->current();

        $this->save([
            'plan' => $plan->value,
            'features' => $this->featuresForPlan($plan),
            'expires_at' => ($expiresAt ?? $this->expiresAt() ?? now()->addYear())->toDateString(),
            'annual_price_inr' => $current['annual_price_inr'] ?? null,
            'client_name' => $current['client_name'] ?? null,
            'notes' => $current['notes'] ?? null,
            'max_students' => $current['max_students'] ?? null,
        ]);
    }

    public function ensureDefaultLicense(): void
    {
        if (! Setting::query()->where('key', self::PAYLOAD_KEY)->exists()) {
            $this->bootstrapFullLicense('Auto-created default license');

            return;
        }

        $signature = Setting::getValue(self::SIGNATURE_KEY);

        if (! is_string($signature) || $signature === '') {
            $this->bootstrapFullLicense('Auto-repaired missing license signature');
        }
    }

    public function repairFullLicense(string $notes = 'Repaired by crm:repair-license'): void
    {
        $this->bootstrapFullLicense($notes);
    }

    protected function bootstrapFullLicense(string $notes): void
    {
        $payload = Setting::getValue(self::PAYLOAD_KEY);
        $current = is_array($payload) ? $payload : [];

        $this->save([
            'plan' => $current['plan'] ?? LicensePlan::Custom->value,
            'features' => LicenseFeature::values(),
            'expires_at' => now()
                ->addDays((int) config('license.default_valid_days', 365))
                ->toDateString(),
            'annual_price_inr' => $current['annual_price_inr'] ?? null,
            'client_name' => $current['client_name'] ?? config('institute.name'),
            'notes' => $notes,
            'max_students' => $current['max_students'] ?? null,
        ]);
    }

    /**
     * @return list<string>
     */
    public function featuresForPlan(LicensePlan $plan): array
    {
        if ($plan === LicensePlan::Custom) {
            return LicenseFeature::values();
        }

        $features = config("license.plan_features.{$plan->value}", []);

        return $this->sanitizeFeatures(is_array($features) ? $features : []);
    }

    /**
     * @param  list<string>|array<int, string>  $features
     * @return list<string>
     */
    public function sanitizeFeatures(array $features): array
    {
        return array_values(array_intersect(LicenseFeature::values(), $features));
    }

    /**
     * @return array{
     *     plan: string,
     *     features: list<string>,
     *     expires_at: string,
     *     annual_price_inr: int|null,
     *     client_name: string|null,
     *     notes: string|null,
     *     max_students: int|null,
     *     updated_at: string,
     * }
     */
    private function defaultPayload(): array
    {
        $plan = LicensePlan::FullResults;

        return [
            'plan' => $plan->value,
            'features' => $this->featuresForPlan($plan),
            'expires_at' => now()->addYear()->endOfDay()->toIso8601String(),
            'annual_price_inr' => null,
            'client_name' => null,
            'notes' => null,
            'max_students' => null,
            'updated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sign(array $payload): string
    {
        $json = json_encode($this->normalizedPayload($payload), JSON_THROW_ON_ERROR);

        return hash_hmac('sha256', $json, $this->signingKey());
    }

    private function signingKey(): string
    {
        $appKey = (string) config('app.key', '');

        if (str_starts_with($appKey, 'base64:')) {
            $decoded = base64_decode(substr($appKey, 7), true);

            if ($decoded !== false) {
                return $decoded;
            }
        }

        return $appKey !== '' ? $appKey : 'school-crm-license';
    }

    private function hasValidStoredSignature(): bool
    {
        $payload = Setting::getValue(self::PAYLOAD_KEY);
        $signature = Setting::getValue(self::SIGNATURE_KEY);

        if (! is_array($payload) || ! is_string($signature) || $signature === '') {
            return false;
        }

        return hash_equals($this->sign($payload), $signature);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizedPayload(array $payload): array
    {
        $features = $payload['features'] ?? [];
        sort($features);

        return [
            'plan' => (string) ($payload['plan'] ?? LicensePlan::Custom->value),
            'features' => array_values($features),
            'expires_at' => (string) ($payload['expires_at'] ?? ''),
            'annual_price_inr' => array_key_exists('annual_price_inr', $payload)
                ? ($payload['annual_price_inr'] === null ? null : (int) $payload['annual_price_inr'])
                : null,
            'client_name' => array_key_exists('client_name', $payload)
                ? ($payload['client_name'] === null ? null : (string) $payload['client_name'])
                : null,
            'notes' => array_key_exists('notes', $payload)
                ? ($payload['notes'] === null ? null : (string) $payload['notes'])
                : null,
            'max_students' => array_key_exists('max_students', $payload)
                ? ($payload['max_students'] === null ? null : (int) $payload['max_students'])
                : null,
        ];
    }
}
