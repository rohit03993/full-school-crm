<?php

namespace App\Filament\Concerns;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use Illuminate\Support\Facades\Auth;

trait RequiresAnyCrmPermission
{
    /**
     * @return list<CrmPermission>
     */
    abstract protected static function anyCrmPermissions(): array;

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return null;
    }

    public static function canAccess(): bool
    {
        $feature = static::requiredLicenseFeature();

        if ($feature !== null && ! FeatureGate::enabled($feature)) {
            return false;
        }

        return CrmAccess::canAny(Auth::user(), ...static::anyCrmPermissions());
    }
}
