<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\InstituteSettings;
use App\Support\SiteContent;

class InstituteSettingsService
{
    public function getFormData(): array
    {
        $g = fn (string $key, mixed $default = null) => Setting::getValue($key, $default);

        return [
            'name' => $g('site.name', config('folks.name')),
            'tagline' => $g('site.tagline', config('folks.tagline')),
            'phone' => $g('site.phone', config('folks.phone')),
            'email' => $g('site.email', config('folks.email')),
            'address' => $g('site.address', config('folks.address')),
            'receipt_logo' => $g('crm.receipt_logo') ?: $g('site.logo'),
            'receipt_header' => $g('crm.receipt_header', ''),
            'receipt_footer' => $g('crm.receipt_footer', config('folks.receipt_footer')),
        ];
    }

    public function save(array $data): void
    {
        $this->persistImage('crm.receipt_logo', $data['receipt_logo'] ?? null);

        Setting::setValue('crm.receipt_header', $data['receipt_header'] ?? '', 'crm');
        Setting::setValue('crm.receipt_footer', $data['receipt_footer'] ?? '', 'crm');

        InstituteSettings::clearCache();
        SiteContent::clearCache();
    }

    protected function persistImage(string $key, mixed $newState): void
    {
        $oldPath = Setting::getValue($key);
        $newPath = SiteImageService::normalizePath($newState);

        SiteImageService::replace($oldPath, $newPath);
        Setting::setValue($key, $newPath ?? '', 'crm');
    }
}
