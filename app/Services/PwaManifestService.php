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
        $brand = InstituteSettings::brandName();
        $name = $brand.$profile['name_suffix'];

        return [
            'name' => $name,
            'short_name' => Str::limit($name, 12, ''),
            'description' => $profile['description'],
            'start_url' => $profile['start_url'],
            'scope' => $profile['scope'],
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#FFFFFF',
            'theme_color' => '#102a43',
            'icons' => [
                [
                    'src' => route('pwa.icon', ['size' => 192]),
                    'sizes' => '192x192',
                    'type' => 'image/png',
                    'purpose' => 'any',
                ],
                [
                    'src' => route('pwa.icon', ['size' => 512]),
                    'sizes' => '512x512',
                    'type' => 'image/png',
                    'purpose' => 'any maskable',
                ],
            ],
        ];
    }

    public static function iconSourcePath(int $size): ?string
    {
        if ($size <= 192) {
            $favicon = (string) Setting::getValue('site.favicon', '');

            if (filled($favicon) && Storage::disk('public')->exists($favicon)) {
                return $favicon;
            }
        }

        $logo = (string) Setting::getValue('site.logo', '');

        if (filled($logo) && Storage::disk('public')->exists($logo)) {
            return $logo;
        }

        $favicon = (string) Setting::getValue('site.favicon', '');

        if (filled($favicon) && Storage::disk('public')->exists($favicon)) {
            return $favicon;
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
            ->take(2)
            ->map(fn (string $word): string => mb_strtoupper(mb_substr($word, 0, 1)))
            ->implode('');

        return $initials !== '' ? $initials : 'SC';
    }

    /**
     * @return array{name_suffix: string, start_url: string, scope: string, description: string}
     */
    private static function profile(string $context): array
    {
        $brand = InstituteSettings::brandName();

        return match ($context) {
            'portal' => [
                'name_suffix' => ' — Student Portal',
                'start_url' => '/portal',
                'scope' => '/portal/',
                'description' => "Student portal for {$brand} — fees, marks, homework, and more.",
            ],
            'admin' => [
                'name_suffix' => ' — Admin',
                'start_url' => '/admin',
                'scope' => '/admin/',
                'description' => "Staff CRM for {$brand} — attendance, leads, fees, and messaging.",
            ],
            default => [
                'name_suffix' => '',
                'start_url' => '/',
                'scope' => '/',
                'description' => "Official website for {$brand}.",
            ],
        };
    }
}
