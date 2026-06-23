<?php

namespace App\Support;

use App\Models\Setting;

class InstituteOnboarding
{
    public const COMPLETED_KEY = 'crm.onboarding_completed';

    public const COMPLETED_AT_KEY = 'crm.onboarding_completed_at';

    public static function isComplete(): bool
    {
        if (filter_var(Setting::getValue(self::COMPLETED_KEY, false), FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $name = trim((string) Setting::getValue('site.name', ''));

        return filled($name) && $name !== 'Your Institute';
    }

    public static function markComplete(): void
    {
        Setting::setValue(self::COMPLETED_KEY, '1', 'crm');
        Setting::setValue(self::COMPLETED_AT_KEY, now()->toIso8601String(), 'crm');
    }

    public static function reset(): void
    {
        Setting::query()->whereIn('key', [self::COMPLETED_KEY, self::COMPLETED_AT_KEY])->delete();
    }
}
