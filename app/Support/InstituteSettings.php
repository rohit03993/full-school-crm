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
                'name' => (string) $g('site.name', config('folks.name')),
                'tagline' => (string) $g('site.tagline', config('folks.tagline')),
                'phone' => (string) $g('site.phone', config('folks.phone')),
                'email' => (string) $g('site.email', config('folks.email')),
                'address' => (string) $g('site.address', config('folks.address')),
                'receipt_header' => (string) $g('crm.receipt_header', ''),
                'footer' => (string) $g('crm.receipt_footer', config('folks.receipt_footer')),
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
