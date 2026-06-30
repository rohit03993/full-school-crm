<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\LicenseFeature;
use App\Support\FeatureGate;

trait VisibleWithLicenseFeature
{
    abstract protected static function licenseFeatureForWidget(): LicenseFeature;

    protected static function licenseAllowsWidget(): bool
    {
        return FeatureGate::enabled(static::licenseFeatureForWidget());
    }
}
