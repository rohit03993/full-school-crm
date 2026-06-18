<?php

namespace App\Support;

use App\Models\Setting;
use App\Services\SiteImageService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class InstituteSettings
{
    public const CACHE_KEY = 'institute_settings.crm';

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function brandName(): string
    {
        return self::forDocuments()['name'];
    }

    public static function numberPrefix(): string
    {
        $raw = Setting::getValue('crm.number_prefix');

        if (filled($raw) && is_string($raw)) {
            $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', $raw));

            if ($prefix !== '') {
                return $prefix;
            }
        }

        $fallback = (string) config('institute.number_prefix', 'CRM');
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', $fallback));

        return $prefix !== '' ? $prefix : 'CRM';
    }

    public static function panelLogoUrl(): ?string
    {
        $path = Setting::getValue('site.logo');

        return SiteImageService::url($path);
    }

    /**
     * Branding used on receipts, ID cards, and CRM PDF exports.
     *
     * @return array{
     *     name: string,
     *     tagline: string,
     *     phone: string,
     *     email: string,
     *     address: string,
     *     receipt_header: string,
     *     footer: string,
     *     logo_data_uri: ?string
     * }
     */
    public static function forDocuments(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $g = fn (string $key, mixed $default = null) => Setting::getValue($key, $default);

            return [
                'name' => (string) $g('site.name', config('institute.name')),
                'tagline' => (string) $g('site.tagline', config('institute.tagline')),
                'phone' => (string) $g('site.phone', config('institute.phone')),
                'email' => (string) $g('site.email', config('institute.email')),
                'address' => (string) $g('site.address', config('institute.address')),
                'receipt_header' => (string) $g('crm.receipt_header', ''),
                'footer' => (string) $g('crm.receipt_footer', config('institute.receipt_footer')),
                'logo_data_uri' => self::logoDataUri(
                    $g('crm.receipt_logo') ?: $g('site.logo'),
                ),
            ];
        });
    }

    public static function logoDataUri(?string $path): ?string
    {
        $path = SiteImageService::normalizePath($path);

        if (blank($path) || str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return null;
        }

        $disk = Storage::disk(SiteImageService::DISK);

        if (! $disk->exists($path)) {
            return null;
        }

        $contents = $disk->get($path);
        $mime = $disk->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
