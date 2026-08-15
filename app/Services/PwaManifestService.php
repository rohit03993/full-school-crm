<?php

namespace App\Services;

use App\Models\Setting;
use App\Support\InstituteSettings;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PwaManifestService
{
    /**
     * @return array<string, mixed>
     */
    public static function manifest(string $context = 'public'): array
    {
        $profile = self::profile($context);

        return [
            'name' => self::displayName($context),
            'short_name' => self::shortName($context),
            'description' => $profile['description'],
            'start_url' => $profile['start_url'],
            'scope' => $profile['scope'],
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#FFFFFF',
            'theme_color' => self::themeColor(),
            'icons' => [
                [
                    'src' => url('/pwa/icon/192'),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => url('/pwa/icon/512'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => url('/pwa/icon/512'),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'maskable',
                ],
            ],
            'id' => $profile['start_url'],
            'lang' => 'en',
            'dir' => 'ltr',
            'categories' => ['education', 'business'],
        ];
    }

    /**
     * Full install title shown in the install dialog.
     */
    public static function displayName(string $context = 'public'): string
    {
        $brand = InstituteSettings::brandName();

        return match ($context) {
            'admin' => $brand.' Admin',
            'portal' => $brand.' Portal',
            default => $brand,
        };
    }

    /**
     * Home-screen label — kept ≤12 characters so Android does not truncate oddly.
     */
    public static function shortName(string $context = 'public'): string
    {
        $token = self::shortBrandToken();

        return match ($context) {
            'admin' => self::fitShort($token, ' Admin'),
            'portal' => self::fitShort($token, ' Portal'),
            default => Str::limit($token, 12, ''),
        };
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

    /**
     * Fit "{token}{suffix}" into 12 characters; fall back to initials when the brand word is long.
     */
    protected static function fitShort(string $token, string $suffix): string
    {
        $max = 12;
        $room = $max - strlen($suffix);

        if ($room < 2) {
            return Str::limit($token.$suffix, $max, '');
        }

        if (strlen($token) <= $room) {
            return $token.$suffix;
        }

        $initials = self::brandInitials();

        if (strlen($initials) <= $room) {
            return $initials.$suffix;
        }

        return Str::limit($token, $room, '').$suffix;
    }

    /**
     * @return array{start_url: string, scope: string, description: string}
     */
    private static function profile(string $context): array
    {
        $brand = InstituteSettings::brandName();

        return match ($context) {
            'portal' => [
                'start_url' => '/portal',
                'scope' => '/portal/',
                'description' => "Student portal for {$brand} — fees, marks, homework, and more.",
            ],
            'admin' => [
                'start_url' => '/admin',
                'scope' => '/admin/',
                'description' => "Staff CRM for {$brand} — attendance, leads, fees, and messaging.",
            ],
            default => [
                'start_url' => '/',
                'scope' => '/',
                'description' => "Official website for {$brand}.",
            ],
        };
    }
}
