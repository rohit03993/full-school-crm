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
     *     logo_data_uri: ?string,
     *     id_card_primary: string,
     *     id_card_primary_dark: string,
     *     id_card_accent: string,
     *     id_card_badge: string,
     *     id_card_header_text: string
     * }
     */
    public static function forDocuments(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $g = fn (string $key, mixed $default = null) => Setting::getValue($key, $default);
            $primary = self::normalizeHexColor($g('crm.id_card_primary_color'), '#1e40af');
            $accent = self::normalizeHexColor($g('crm.id_card_accent_color'), '#dc2626');
            $badge = self::normalizeHexColor($g('crm.id_card_badge_color'), '#fbbf24');

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
                'id_card_primary' => $primary,
                'id_card_primary_dark' => self::darkenHex($primary, 0.18),
                'id_card_accent' => $accent,
                'id_card_badge' => $badge,
                'id_card_header_text' => self::darkenHex($primary, 0.35),
            ];
        });
    }

    /**
     * Marksheet / report card template options for PDF generation.
     *
     * @return array{
     *     title: string,
     *     footer_note: string,
     *     signature_title: string,
     *     signature_name: string,
     *     show_rank: bool,
     *     show_attendance: bool,
     *     show_subject_remarks: bool,
     *     show_principal_remarks: bool
     * }
     */
    public static function forMarksheets(): array
    {
        $documents = self::forDocuments();
        $g = fn (string $key, mixed $default = null) => Setting::getValue($key, $default);

        return [
            'name' => $documents['name'],
            'tagline' => $documents['tagline'],
            'address' => $documents['address'],
            'receipt_header' => $documents['receipt_header'],
            'footer' => $documents['footer'],
            'logo_data_uri' => $documents['logo_data_uri'],
            'title' => (string) $g('marksheet.title', 'Statement of Marks'),
            'footer_note' => (string) $g('marksheet.footer_note', 'This is a computer-generated marksheet. Collect the official signed copy from the institute office if required.'),
            'signature_title' => (string) $g('marksheet.signature_title', 'Controller of Examination'),
            'signature_name' => (string) $g('marksheet.signature_name', ''),
            'show_rank' => filter_var($g('marksheet.show_rank', true), FILTER_VALIDATE_BOOLEAN),
            'show_attendance' => filter_var($g('marksheet.show_attendance', true), FILTER_VALIDATE_BOOLEAN),
            'show_subject_remarks' => filter_var($g('marksheet.show_subject_remarks', false), FILTER_VALIDATE_BOOLEAN),
            'show_principal_remarks' => filter_var($g('marksheet.show_principal_remarks', true), FILTER_VALIDATE_BOOLEAN),
        ];
    }

    /**
     * @return array{first: float, second: float, pass: float}
     */
    public static function marksheetDivisionThresholds(): array
    {
        $get = fn (string $key, mixed $default): float => (float) Setting::getValue($key, $default);

        return [
            'first' => max(0, $get('marksheet.division_first', 55)),
            'second' => max(0, $get('marksheet.division_second', 48)),
            'pass' => max(0, $get('marksheet.division_pass', 40)),
        ];
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

    public static function normalizeHexColor(mixed $value, string $fallback): string
    {
        $raw = strtoupper(trim((string) $value));

        if (preg_match('/^#?[0-9A-F]{6}$/', $raw) !== 1) {
            return strtoupper($fallback);
        }

        return str_starts_with($raw, '#') ? $raw : '#'.$raw;
    }

    /**
     * Darken a #RRGGBB color by a 0–1 factor (DomPDF-safe solid colors).
     */
    public static function darkenHex(string $hex, float $factor): string
    {
        $hex = ltrim(self::normalizeHexColor($hex, '#1e40af'), '#');
        $factor = max(0, min(1, $factor));

        $r = max(0, (int) round(hexdec(substr($hex, 0, 2)) * (1 - $factor)));
        $g = max(0, (int) round(hexdec(substr($hex, 2, 2)) * (1 - $factor)));
        $b = max(0, (int) round(hexdec(substr($hex, 4, 2)) * (1 - $factor)));

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }
}
