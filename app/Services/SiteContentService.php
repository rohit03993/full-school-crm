<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SiteGalleryItem;
use App\Support\InstituteSettings;
use App\Support\SiteContent;

class SiteContentService
{
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
            'site.name' => ['value' => config('institute.name'), 'group' => 'general'],
            'site.tagline' => ['value' => config('institute.tagline'), 'group' => 'general'],
            'site.phone' => ['value' => config('institute.phone'), 'group' => 'contact'],
            'site.email' => ['value' => config('institute.email'), 'group' => 'contact'],
            'site.whatsapp' => ['value' => config('institute.whatsapp'), 'group' => 'contact'],
            'site.address' => ['value' => config('institute.address'), 'group' => 'contact'],
            'site.city' => ['value' => config('institute.city'), 'group' => 'contact'],
            'site.hours' => ['value' => config('institute.hours'), 'group' => 'contact'],
            'site.established' => ['value' => config('institute.established'), 'group' => 'general'],
            'site.hero_title' => ['value' => config('institute.hero.title'), 'group' => 'hero'],
            'site.hero_subtitle' => ['value' => config('institute.hero.subtitle'), 'group' => 'hero'],
            'site.about' => ['value' => config('institute.about'), 'group' => 'about'],
            'site.highlights' => [
                'value' => [
                    ['value' => '15+', 'label' => 'Years of Excellence'],
                    ['value' => '5000+', 'label' => 'Students Enrolled'],
                    ['value' => '100%', 'label' => 'Dedicated Faculty'],
                    ['value' => '10+', 'label' => 'Programme Options'],
                ],
                'group' => 'home',
            ],
            'crm.receipt_footer' => ['value' => config('institute.receipt_footer'), 'group' => 'crm'],
            'crm.number_prefix' => ['value' => config('institute.number_prefix', 'CRM'), 'group' => 'crm'],
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
                ['value' => '5000+', 'label' => 'Students Enrolled'],
                ['value' => '100%', 'label' => 'Dedicated Faculty'],
                ['value' => '10+', 'label' => 'Programme Options'],
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
