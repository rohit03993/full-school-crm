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

        return collect(config('institute.images.gallery', []))->map(fn (array $item, int $index) => (object) [
            'image_url' => $item['src'],
            'alt' => $item['alt'],
            'caption' => $item['caption'],
            'span_class' => $item['span'] ?? '',
            'sort_order' => $index,
        ]);
    }

    protected static function galleryItemSrc(object $item): string
    {
        if (isset($item->image_path) && filled($item->image_path)) {
            return SiteImageService::url($item->image_path) ?? '';
        }

        return (string) ($item->image_url ?? '');
    }

    protected static function buildInstituteArray(): array
    {
        $g = fn (string $key, mixed $default = null) => Setting::getValue($key, $default);

        return [
            'name' => $g('site.name', config('institute.name')),
            'tagline' => $g('site.tagline', config('institute.tagline')),
            'hero' => [
                'title' => $g('site.hero_title', config('institute.hero.title')),
                'subtitle' => $g('site.hero_subtitle', config('institute.hero.subtitle')),
            ],
            'about' => $g('site.about', config('institute.about')),
            'phone' => $g('site.phone', config('institute.phone')),
            'whatsapp' => $g('site.whatsapp', config('institute.whatsapp')),
            'email' => $g('site.email', config('institute.email')),
            'address' => $g('site.address', config('institute.address')),
            'city' => $g('site.city', config('institute.city')),
            'hours' => $g('site.hours', config('institute.hours')),
            'established' => $g('site.established', config('institute.established')),
            'social' => [
                'facebook' => $g('site.social_facebook', config('institute.social.facebook')),
                'instagram' => $g('site.social_instagram', config('institute.social.instagram')),
                'youtube' => $g('site.social_youtube', config('institute.social.youtube')),
            ],
            'logo_url' => SiteImageService::url($g('site.logo')),
            'favicon_url' => SiteImageService::url($g('site.favicon')),
            'images' => [
                'hero' => [
                    'main' => SiteImageService::url($g('site.hero_main_image')) ?? config('institute.images.hero.main'),
                    'accent_one' => SiteImageService::url($g('site.hero_accent_one')) ?? config('institute.images.hero.accent_one'),
                    'accent_two' => SiteImageService::url($g('site.hero_accent_two')) ?? config('institute.images.hero.accent_two'),
                    'about' => SiteImageService::url($g('site.about_image')) ?? config('institute.images.hero.about'),
                ],
                'gallery' => self::galleryItems()->map(fn ($item) => [
                    'src' => self::galleryItemSrc($item),
                    'alt' => $item->alt,
                    'caption' => $item->caption,
                    'span' => $item->span_class ?? '',
                ])->values()->all(),
            ],
            'highlights' => $g('site.highlights', [
                ['value' => '15+', 'label' => 'Years of Excellence'],
                ['value' => '500+', 'label' => 'Students Enrolled'],
                ['value' => '100%', 'label' => 'Dedicated Faculty'],
                ['value' => '10+', 'label' => 'Programme Options'],
            ]),
            'home' => [
                'about_eyebrow' => $g('site.home_about_eyebrow', 'About Us'),
                'about_title' => $g('site.home_about_title', 'Training the next generation of learners'),
                'about_points' => $g('site.home_about_points', []),
                'about_cta' => $g('site.home_about_cta', 'Learn more about admissions'),
                'courses_eyebrow' => $g('site.home_courses_eyebrow', 'Our Programmes'),
                'courses_title' => $g('site.home_courses_title', 'Courses designed for real careers'),
                'courses_subtitle' => $g('site.home_courses_subtitle', 'Choose the programme that fits your goals.'),
                'show_courses_section' => (bool) $g('site.home_show_courses_section', true),
                'cta_title' => $g('site.home_cta_title', 'Ready to start your learning journey?'),
                'cta_subtitle' => $g('site.home_cta_subtitle', 'Visit our campus, speak with our counsellors, or call us to learn more about admissions.'),
            ],
            'hero_stats' => $g('site.hero_stats', []),
        ];
    }
}
