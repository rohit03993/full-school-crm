<?php

namespace App\Services\CallIntelligence;

use App\Models\Setting;
use Illuminate\Support\Facades\Schema;

class CallIntelligenceSettings
{
    public const GROUP = 'call_intelligence';

    public function apply(): void
    {
        if (! $this->tableReady()) {
            return;
        }

        $map = [
            'api_url' => 'call_intelligence.api_url',
            'school_code' => 'call_intelligence.school_code',
            'school_secret' => 'call_intelligence.school_secret',
            'callback_secret' => 'call_intelligence.callback_secret',
        ];

        foreach ($map as $configKey => $settingKey) {
            $value = Setting::getValue($settingKey);

            if (filled($value)) {
                config(["call_intelligence.{$configKey}" => $value]);
            }
        }

        $enabled = Setting::getValue('call_intelligence.enabled');

        if ($enabled !== null && $enabled !== '') {
            config(['call_intelligence.enabled' => filter_var($enabled, FILTER_VALIDATE_BOOLEAN)]);
        }
    }

    public function enabled(): bool
    {
        $this->apply();

        return (bool) config('call_intelligence.enabled')
            && filled(config('call_intelligence.api_url'))
            && filled(config('call_intelligence.school_code'))
            && filled(config('call_intelligence.school_secret'))
            && filled(config('call_intelligence.callback_secret'));
    }

    public function value(string $key, mixed $default = null): mixed
    {
        $this->apply();

        return config('call_intelligence.'.$key, $default);
    }

    private function tableReady(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (\Throwable) {
            return false;
        }
    }
}
