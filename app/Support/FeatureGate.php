<?php

namespace App\Support;

use App\Enums\LicenseFeature;
use App\Services\LicenseService;

class FeatureGate
{
    public static function enabled(LicenseFeature|string $feature): bool
    {
        return app(LicenseService::class)->hasFeature($feature);
    }

    public static function licenseActive(): bool
    {
        return app(LicenseService::class)->isActive();
    }

    public static function licenseExpired(): bool
    {
        $service = app(LicenseService::class);

        return ! $service->isSignatureValid() || $service->isExpired();
    }
}
