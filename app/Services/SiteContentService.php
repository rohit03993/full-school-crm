<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SiteGalleryItem;
use App\Support\InstituteSettings;
use App\Support\SiteContent;

class SiteContentService
{
    /** Unsplash photo IDs that were removed upstream — swap for working defaults. */
    public const REMOVED_UNSPLASH_REPLACEMENTS = [
        'photo-1523050854058-8df90110c9f1' => 'photo-1562774053-701939374585',
        'photo-1541339907198-e08756dedf3d' => 'photo-1427504494785-3a9ca7044f45',
    ];

    /** @var array<string, string> */
    protected array $imageKeys = [
        'logo' => 'site.logo',
        'favicon' => 'site.favicon',
        'hero_main_image' => 'site.hero_main_image',
        'hero_accent_one' => 'site.hero_accent_one',
        'hero_accent_two' => 'site.hero_accent_two',
        'about_image' => 'site.about_image',
    ];

    /**
     * @return array<string, array{value: mixed, group: string}>
     */
    public function defaultSiteSettings(): array
    {
        return [
            'site.name' => ['value' => 'Your Institute', 'group' => 'general'],
            'site.tagline' => ['value' => 'School & Coaching Management', 'group' => 'general'],
            'site.phone' => ['value' => '', 'group' => 'contact'],
            'site.email' => ['value' => '', 'group' => 'contact'],
            'site.whatsapp' => ['value' => '', 'group' => 'contact'],
            'site.address' => ['value' => '', 'group' => 'contact'],
            'site.city' => ['value' => '', 'group' => 'contact'],
            'site.hours' => ['value' => 'Mon – Sat: 9:00 AM – 6:00 PM', 'group' => 'contact'],
            'site.established' => ['value' => (string) now()->year, 'group' => 'general'],
            'site.hero_title' => ['value' => 'Quality Education for Every Student', 'group' => 'hero'],
            'site.hero_subtitle' => ['value' => 'Manage admissions, fees, batches, and attendance — built for schools, colleges, and coaching institutes.', 'group' => 'hero'],
            'site.about' => ['value' => 'We are focused on academic excellence and student success. From classroom programmes to competitive exam coaching, we help students achieve their goals.', 'group' => 'about'],
            'site.highlights' => [
                'value' => [
                    ['value' => '15+', 'label' => 'Years of Excellence'],
                    ['value' => '500+', 'label' => 'Students Enrolled'],
                    ['value' => '100%', 'label' => 'Dedicated Faculty'],
                    ['value' => '10+', 'label' => 'Programme Options'],
                ],
                'group' => 'home',
            ],
            'site.home_about_eyebrow' => ['value' => 'About Us', 'group' => 'home'],
            'site.home_about_title' => ['value' => 'Training the next generation of learners', 'group' => 'home'],
            'site.home_about_points' => [
                'value' => [
                    ['text' => 'Experienced faculty and mentors'],
                    ['text' => 'Structured programmes and flexible batches'],
                    ['text' => 'Guidance from enquiry to enrollment'],
                ],
                'group' => 'home',
            ],
            'site.home_about_cta' => ['value' => 'Learn more about admissions', 'group' => 'home'],
            'site.home_courses_eyebrow' => ['value' => 'Our Programmes', 'group' => 'home'],
            'site.home_courses_title' => ['value' => 'Courses designed for real careers', 'group' => 'home'],
            'site.home_courses_subtitle' => ['value' => 'Choose the programme that fits your goals.', 'group' => 'home'],
            'site.home_show_courses_section' => ['value' => true, 'group' => 'home'],
            'site.home_cta_title' => ['value' => 'Ready to start your learning journey?', 'group' => 'home'],
            'site.home_cta_subtitle' => ['value' => 'Visit our campus, speak with our counsellors, or call us to learn more about admissions.', 'group' => 'home'],
            'site.hero_stats' => [
                'value' => [
                    ['title' => 'Programmes', 'subtitle' => 'Courses on offer'],
                    ['title' => 'Batches', 'subtitle' => 'Flexible groups'],
                    ['title' => '100%', 'subtitle' => 'Student focus'],
                ],
                'group' => 'hero',
            ],
            'crm.receipt_footer' => ['value' => config('institute.receipt_footer'), 'group' => 'crm'],
            'crm.number_prefix' => ['value' => 'CRM', 'group' => 'crm'],
        ];
    }

    public function syncDefaultsFromConfig(): void
    {
        foreach ($this->defaultSiteSettings() as $key => $meta) {
            Setting::setValue($key, $meta['value'], $meta['group']);
        }

        SiteContent::clearCache();
        InstituteSettings::clearCache();
    }

    public function repairBrokenRemoteImageUrls(): int
    {
        $fixed = 0;

        foreach ($this->imageKeys as $settingKey) {
            $path = Setting::getValue($settingKey);

            if (blank($path)) {
                continue;
            }

            $repaired = $this->replaceBrokenRemoteUrl($path);

            if ($repaired !== $path) {
                Setting::setValue($settingKey, $repaired, 'images');
                $fixed++;
            }
        }

        foreach (SiteGalleryItem::query()->get() as $item) {
            $repaired = $this->replaceBrokenRemoteUrl($item->image_path);

            if ($repaired !== $item->image_path) {
                $item->update(['image_path' => $repaired]);
                $fixed++;
            }
        }

        if ($fixed > 0) {
            SiteContent::clearCache();
            InstituteSettings::clearCache();
        }

        return $fixed;
    }

    protected function replaceBrokenRemoteUrl(?string $url): ?string
    {
        if (blank($url)) {
            return $url;
        }

        foreach (self::REMOVED_UNSPLASH_REPLACEMENTS as $broken => $replacement) {
            if (str_contains($url, $broken)) {
                return str_replace($broken, $replacement, $url);
            }
        }

        return $url;
    }

    public function getFormData(): array
    {
        $g = fn (string $key, mixed $default = null) => Setting::getValue($key, $default);

        return [
            'name' => $g('site.name', config('institute.name')),
            'tagline' => $g('site.tagline', config('institute.tagline')),
            'number_prefix' => $g('crm.number_prefix', config('institute.number_prefix', 'CRM')),
            'phone' => $g('site.phone', config('institute.phone')),
            'email' => $g('site.email', config('institute.email')),
            'whatsapp' => $g('site.whatsapp', config('institute.whatsapp')),
            'address' => $g('site.address', config('institute.address')),
            'city' => $g('site.city', config('institute.city')),
            'hours' => $g('site.hours', config('institute.hours')),
            'established' => $g('site.established', config('institute.established')),
            'hero_title' => $g('site.hero_title', config('institute.hero.title')),
            'hero_subtitle' => $g('site.hero_subtitle', config('institute.hero.subtitle')),
            'about' => $g('site.about', config('institute.about')),
            'social_facebook' => $g('site.social_facebook', ''),
            'social_instagram' => $g('site.social_instagram', ''),
            'social_youtube' => $g('site.social_youtube', ''),
            'logo' => $g('site.logo'),
            'favicon' => $g('site.favicon'),
            'hero_main_image' => $g('site.hero_main_image'),
            'hero_accent_one' => $g('site.hero_accent_one'),
            'hero_accent_two' => $g('site.hero_accent_two'),
            'about_image' => $g('site.about_image'),
            'highlights' => $g('site.highlights', [
                ['value' => '15+', 'label' => 'Years of Excellence'],
                ['value' => '500+', 'label' => 'Students Enrolled'],
                ['value' => '100%', 'label' => 'Dedicated Faculty'],
                ['value' => '10+', 'label' => 'Programme Options'],
            ]),
            'home_about_eyebrow' => $g('site.home_about_eyebrow', 'About Us'),
            'home_about_title' => $g('site.home_about_title', 'Training the next generation of learners'),
            'home_about_points' => $g('site.home_about_points', [
                ['text' => 'Experienced faculty and mentors'],
                ['text' => 'Structured programmes and flexible batches'],
                ['text' => 'Guidance from enquiry to enrollment'],
            ]),
            'home_about_cta' => $g('site.home_about_cta', 'Learn more about admissions'),
            'home_courses_eyebrow' => $g('site.home_courses_eyebrow', 'Our Programmes'),
            'home_courses_title' => $g('site.home_courses_title', 'Courses designed for real careers'),
            'home_courses_subtitle' => $g('site.home_courses_subtitle', 'Choose the programme that fits your goals.'),
            'home_show_courses_section' => (bool) $g('site.home_show_courses_section', true),
            'home_cta_title' => $g('site.home_cta_title', 'Ready to start your learning journey?'),
            'home_cta_subtitle' => $g('site.home_cta_subtitle', 'Visit our campus, speak with our counsellors, or call us to learn more about admissions.'),
            'hero_stats' => $g('site.hero_stats', [
                ['title' => 'Programmes', 'subtitle' => 'Courses on offer'],
                ['title' => 'Batches', 'subtitle' => 'Flexible groups'],
                ['title' => '100%', 'subtitle' => 'Student focus'],
            ]),
            'gallery_items' => SiteGalleryItem::query()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (SiteGalleryItem $item) => [
                    'id' => $item->id,
                    'image_path' => $item->image_path,
                    'alt' => $item->alt,
                    'caption' => $item->caption,
                    'span_class' => $item->span_class,
                ])
                ->values()
                ->all(),
        ];
    }

    public function save(array $data): void
    {
        $this->persistImage('site.logo', $data['logo'] ?? null);
        $this->persistImage('site.favicon', $data['favicon'] ?? null);
        $this->persistImage('site.hero_main_image', $data['hero_main_image'] ?? null);
        $this->persistImage('site.hero_accent_one', $data['hero_accent_one'] ?? null);
        $this->persistImage('site.hero_accent_two', $data['hero_accent_two'] ?? null);
        $this->persistImage('site.about_image', $data['about_image'] ?? null);

        Setting::setValue('site.name', $data['name'] ?? '', 'general');
        Setting::setValue('site.tagline', $data['tagline'] ?? '', 'general');
        Setting::setValue('site.established', $data['established'] ?? '', 'general');
        Setting::setValue(
            'crm.number_prefix',
            $this->normalizeNumberPrefix($data['number_prefix'] ?? null),
            'crm',
        );
        Setting::setValue('site.phone', $data['phone'] ?? '', 'contact');
        Setting::setValue('site.email', $data['email'] ?? '', 'contact');
        Setting::setValue('site.whatsapp', $data['whatsapp'] ?? '', 'contact');
        Setting::setValue('site.address', $data['address'] ?? '', 'contact');
        Setting::setValue('site.city', $data['city'] ?? '', 'contact');
        Setting::setValue('site.hours', $data['hours'] ?? '', 'contact');
        Setting::setValue('site.hero_title', $data['hero_title'] ?? '', 'hero');
        Setting::setValue('site.hero_subtitle', $data['hero_subtitle'] ?? '', 'hero');
        Setting::setValue('site.about', $data['about'] ?? '', 'about');
        Setting::setValue('site.social_facebook', $data['social_facebook'] ?? '', 'social');
        Setting::setValue('site.social_instagram', $data['social_instagram'] ?? '', 'social');
        Setting::setValue('site.social_youtube', $data['social_youtube'] ?? '', 'social');
        Setting::setValue('site.highlights', $data['highlights'] ?? [], 'home');
        Setting::setValue('site.home_about_eyebrow', $data['home_about_eyebrow'] ?? '', 'home');
        Setting::setValue('site.home_about_title', $data['home_about_title'] ?? '', 'home');
        Setting::setValue('site.home_about_points', $data['home_about_points'] ?? [], 'home');
        Setting::setValue('site.home_about_cta', $data['home_about_cta'] ?? '', 'home');
        Setting::setValue('site.home_courses_eyebrow', $data['home_courses_eyebrow'] ?? '', 'home');
        Setting::setValue('site.home_courses_title', $data['home_courses_title'] ?? '', 'home');
        Setting::setValue('site.home_courses_subtitle', $data['home_courses_subtitle'] ?? '', 'home');
        Setting::setValue('site.home_show_courses_section', (bool) ($data['home_show_courses_section'] ?? true), 'home');
        Setting::setValue('site.home_cta_title', $data['home_cta_title'] ?? '', 'home');
        Setting::setValue('site.home_cta_subtitle', $data['home_cta_subtitle'] ?? '', 'home');
        Setting::setValue('site.hero_stats', $data['hero_stats'] ?? [], 'hero');

        $this->syncGallery($data['gallery_items'] ?? []);

        SiteContent::clearCache();
        InstituteSettings::clearCache();
    }

    protected function persistImage(string $key, mixed $newState): void
    {
        $oldPath = Setting::getValue($key);
        $newPath = SiteImageService::normalizePath($newState);

        SiteImageService::replace($oldPath, $newPath);
        Setting::setValue($key, $newPath ?? '', 'images');
    }

    protected function syncGallery(array $items): void
    {
        $existing = SiteGalleryItem::query()->get()->keyBy('id');
        $keptIds = [];

        foreach ($items as $index => $item) {
            $imagePath = SiteImageService::normalizePath($item['image_path'] ?? null);

            if (blank($imagePath)) {
                continue;
            }

            $id = $item['id'] ?? null;
            $record = $id && $existing->has($id) ? $existing->get($id) : new SiteGalleryItem;

            if ($record->exists && $record->image_path !== $imagePath) {
                SiteImageService::replace($record->image_path, $imagePath);
            }

            $record->fill([
                'image_path' => $imagePath,
                'alt' => $item['alt'] ?? '',
                'caption' => $item['caption'] ?? '',
                'span_class' => $item['span_class'] ?? '',
                'sort_order' => $index,
            ]);
            $record->save();

            $keptIds[] = $record->id;
        }

        SiteGalleryItem::query()
            ->when(count($keptIds) > 0, fn ($q) => $q->whereNotIn('id', $keptIds))
            ->when(count($keptIds) === 0, fn ($q) => $q)
            ->get()
            ->each->delete();
    }

    protected function normalizeNumberPrefix(mixed $value): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string) ($value ?? '')));

        if ($prefix === '') {
            $prefix = strtoupper((string) config('institute.number_prefix', 'CRM'));
        }

        return $prefix !== '' ? $prefix : 'CRM';
    }
}
