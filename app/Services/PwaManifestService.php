<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\InstituteSettings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PwaManifestService
{
    public const START_URL = '/app';

    public const SCOPE = '/';

    /**
     * One institute app for staff, parents, and the public site.
     *
     * Legacy context arguments (admin/portal/public) are ignored so every
     * install surface points at the same home-screen app. /app then routes
     * by who is signed in.
     *
     * @return array<string, mixed>
     */
    public static function manifest(string $context = 'app'): array
    {
        unset($context);

        $brand = InstituteSettings::brandName();

        return [
            'name' => self::displayName(),
            'short_name' => self::shortName(),
            'description' => "{$brand} — staff CRM, parent portal, and institute website in one app.",
            'start_url' => self::START_URL,
            'scope' => self::SCOPE,
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#FFFFFF',
            'theme_color' => self::themeColor(),
            'icons' => [
                [
                    'src' => self::iconUrl(192),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => self::iconUrl(512),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => self::iconUrl(512),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            // Stable id so admin/portal/public pages do not create three apps.
            'id' => self::START_URL,
            'lang' => 'en',
            'dir' => 'ltr',
            'categories' => ['education', 'business'],
        ];
    }

    /**
     * Full install title — always the institute name (one app).
     */
    public static function displayName(string $context = 'app'): string
    {
        unset($context);

        return InstituteSettings::brandName();
    }

    /**
     * Home-screen label — kept ≤12 characters so Android does not truncate oddly.
     */
    public static function shortName(string $context = 'app'): string
    {
        unset($context);

        return Str::limit(self::shortBrandToken(), 12, '');
    }

    public static function themeColor(): string
    {
        return InstituteSettings::normalizeHexColor(
            Setting::getValue('crm.id_card_primary_color'),
            '#102a43',
        );
    }

    /**
     * Source image for the installed-app / home-screen icon.
     *
     * Favicon wins for every size — it is the dedicated square mark. The wide
     * website logo is only a fallback (it shrinks awkwardly inside a square
     * when used as an app icon). With neither file, the controller draws initials.
     */
    public static function iconSourcePath(int $size = 512): ?string
    {
        unset($size); // same source for 192 and 512 — GD resizes it.

        $favicon = (string) Setting::getValue('site.favicon', '');

        if (filled($favicon) && Storage::disk('public')->exists($favicon)) {
            return $favicon;
        }

        $logo = (string) Setting::getValue('site.logo', '');

        if (filled($logo) && Storage::disk('public')->exists($logo)) {
            return $logo;
        }

        return null;
    }

    public static function fallbackIconPath(): string
    {
        return public_path('favicon.svg');
    }

    /**
     * Icon URL carrying a token for the current branding.
     *
     * /pwa/icon/{size} is a fixed path that the service worker caches
     * cache-first, so without this token a newly uploaded favicon would never
     * reach an already-installed app or a browser that has the old icon.
     */
    public static function iconUrl(int $size): string
    {
        return url('/pwa/icon/'.$size).'?v='.self::iconVersion();
    }

    public static function iconVersion(): string
    {
        $source = self::iconSourcePath();

        $token = $source !== null
            ? (string) Storage::disk('public')->lastModified($source)
            : 'initials';

        return substr(md5($token.'|'.InstituteSettings::brandName()), 0, 8);
    }

    public static function brandInitials(): string
    {
        $words = preg_split('/\s+/', InstituteSettings::brandName()) ?: [];

        $initials = collect($words)
            ->filter()
            ->take(3)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'SC';
    }

    /**
     * First word of the brand, letters/digits only — used for the home-screen label.
     */
    protected static function shortBrandToken(): string
    {
        $brand = trim(InstituteSettings::brandName());
        $first = strtok($brand, " \t") ?: $brand;
        $clean = preg_replace('/[^A-Za-z0-9]/', '', (string) $first) ?? '';

        if ($clean !== '') {
            return $clean;
        }

        return self::brandInitials();
    }
}
