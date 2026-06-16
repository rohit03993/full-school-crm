<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\SiteGalleryItem;
use App\Services\SiteImageService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class SiteContent
{
    public const CACHE_KEY = 'site_content.institute';

    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public static function institute(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            return self::buildInstituteArray();
        });
    }

    public static function imageUrl(string $settingKey, ?string $fallback = null): ?string
    {
        $path = Setting::getValue($settingKey, $fallback);

        return SiteImageService::url($path) ?? $fallback;
    }

    public static function galleryItems(): Collection
    {
        $items = SiteGalleryItem::query()->orderBy('sort_order')->get();

        if ($items->isNotEmpty()) {
            return $items;
        }

        return collect(config('folks.images.gallery', []))->map(fn (array $item, int $index) => (object) [
            'image_url' => $item['src'],
            'alt' => $item['alt'],
            'caption' => $item['caption'],
            'span_class' => $item['span'] ?? '',
            'sort_order' => $index,
        ]);
    }

    protected static function buildInstituteArray(): array
    {
        $g = fn (string $key, mixed $default = null) => Setting::getValue($key, $default);

        return [
            'name' => $g('site.name', config('folks.name')),
            'tagline' => $g('site.tagline', config('folks.tagline')),
            'hero' => [
                'title' => $g('site.hero_title', config('folks.hero.title')),
                'subtitle' => $g('site.hero_subtitle', config('folks.hero.subtitle')),
            ],
            'about' => $g('site.about', config('folks.about')),
            'phone' => $g('site.phone', config('folks.phone')),
            'whatsapp' => $g('site.whatsapp', config('folks.whatsapp')),
            'email' => $g('site.email', config('folks.email')),
            'address' => $g('site.address', config('folks.address')),
            'city' => $g('site.city', config('folks.city')),
            'hours' => $g('site.hours', config('folks.hours')),
            'established' => $g('site.established', config('folks.established')),
            'social' => [
                'facebook' => $g('site.social_facebook', config('folks.social.facebook')),
                'instagram' => $g('site.social_instagram', config('folks.social.instagram')),
                'youtube' => $g('site.social_youtube', config('folks.social.youtube')),
            ],
            'logo_url' => SiteImageService::url($g('site.logo')),
            'favicon_url' => SiteImageService::url($g('site.favicon')),
            'images' => [
                'hero' => [
                    'main' => SiteImageService::url($g('site.hero_main_image')) ?? config('folks.images.hero.main'),
                    'accent_one' => SiteImageService::url($g('site.hero_accent_one')) ?? config('folks.images.hero.accent_one'),
                    'accent_two' => SiteImageService::url($g('site.hero_accent_two')) ?? config('folks.images.hero.accent_two'),
                    'about' => SiteImageService::url($g('site.about_image')) ?? config('folks.images.hero.about'),
                ],
                'gallery' => self::galleryItems()->map(fn ($item) => [
                    'src' => $item->image_url ?? SiteImageService::url($item->image_path ?? null),
                    'alt' => $item->alt,
                    'caption' => $item->caption,
                    'span' => $item->span_class ?? '',
                ])->values()->all(),
            ],
            'highlights' => $g('site.highlights', [
                ['value' => '15+', 'label' => 'Years of Training'],
                ['value' => '5000+', 'label' => 'Students Trained'],
                ['value' => '100%', 'label' => 'Practical Focus'],
                ['value' => '4+', 'label' => 'Programme Options'],
            ]),
        ];
    }
}
