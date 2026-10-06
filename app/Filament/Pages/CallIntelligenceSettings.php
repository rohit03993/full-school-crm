<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Models\Setting;
use App\Services\CallIntelligence\CallIntelligenceSettings as CallIntelligenceConfig;
use App\Support\CrmAccess;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CallIntelligenceSettings extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedMicrophone;

    protected static ?string $title = 'Call AI';

    protected static ?string $slug = 'call-intelligence';

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    protected string $view = 'filament.pages.call-intelligence-settings';

    public bool $enabled = false;

    public string $apiUrl = '';

    public string $schoolCode = '';

    public string $schoolSecret = '';

    public string $callbackSecret = '';

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Calls)) {
            return false;
        }

        $user = Auth::user();

        return ($user?->hasRole(RoleName::SuperAdmin->value) ?? false)
            || CrmAccess::can($user, CrmPermission::SettingsManage);
    }

    public function mount(CallIntelligenceConfig $settings): void
    {
        $settings->apply();
        $this->enabled = (bool) config('call_intelligence.enabled');
        $this->apiUrl = (string) config('call_intelligence.api_url');
        $this->schoolCode = (string) config('call_intelligence.school_code');
        $this->schoolSecret = (string) config('call_intelligence.school_secret');
        $this->callbackSecret = (string) config('call_intelligence.callback_secret');
    }

    public function save(): void
    {
        $this->validate([
            'apiUrl' => [$this->enabled ? 'required' : 'nullable', 'url'],
            'schoolCode' => [$this->enabled ? 'required' : 'nullable', 'string', 'max:80'],
            'schoolSecret' => [$this->enabled ? 'required' : 'nullable', 'string', 'max:255'],
            'callbackSecret' => [$this->enabled ? 'required' : 'nullable', 'string', 'max:255'],
        ]);

        Setting::setValue('call_intelligence.enabled', $this->enabled ? '1' : '0', CallIntelligenceConfig::GROUP);
        Setting::setValue('call_intelligence.api_url', $this->apiUrl, CallIntelligenceConfig::GROUP);
        Setting::setValue('call_intelligence.school_code', $this->schoolCode, CallIntelligenceConfig::GROUP);
        Setting::setValue('call_intelligence.school_secret', $this->schoolSecret, CallIntelligenceConfig::GROUP);
        Setting::setValue('call_intelligence.callback_secret', $this->callbackSecret, CallIntelligenceConfig::GROUP);
        Setting::flushValueCache();

        Notification::make()->title('Call AI saved')->success()->send();
    }
}
