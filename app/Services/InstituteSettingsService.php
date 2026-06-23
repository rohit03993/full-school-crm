<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\InstituteSettings;
use App\Support\SiteContent;

class InstituteSettingsService
{
    public function __construct(
        protected StudentAuthService $studentAuth,
    ) {}

    public function getFormData(): array
    {
        $g = fn (string $key, mixed $default = null) => Setting::getValue($key, $default);

        return [
            'name' => $g('site.name', config('institute.name')),
            'tagline' => $g('site.tagline', config('institute.tagline')),
            'phone' => $g('site.phone', config('institute.phone')),
            'email' => $g('site.email', config('institute.email')),
            'address' => $g('site.address', config('institute.address')),
            'receipt_logo' => $g('crm.receipt_logo') ?: $g('site.logo'),
            'receipt_header' => $g('crm.receipt_header', ''),
            'receipt_footer' => $g('crm.receipt_footer', config('institute.receipt_footer')),
            'portal_shared_password' => '',
        ];
    }

    public function save(array $data): void
    {
        $this->persistImage('crm.receipt_logo', $data['receipt_logo'] ?? null);

        Setting::setValue('crm.receipt_header', $data['receipt_header'] ?? '', 'crm');
        Setting::setValue('crm.receipt_footer', $data['receipt_footer'] ?? '', 'crm');

        Setting::setValue('portal.login_mode', StudentAuthService::LOGIN_MODE_SHARED, 'portal');

        $sharedPlain = trim((string) ($data['portal_shared_password'] ?? ''));

        if ($sharedPlain !== '') {
            Setting::setValue(
                'portal.shared_password_hash',
                $this->studentAuth->hashPortalPassword($sharedPlain),
                'portal',
            );
        } else {
            $this->studentAuth->sharedPortalPasswordHash();
        }

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
