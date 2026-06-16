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

    public function getFormData(): array
    {
        $g = fn (string $key, mixed $default = null) => Setting::getValue($key, $default);

        return [
            'name' => $g('site.name', config('folks.name')),
            'tagline' => $g('site.tagline', config('folks.tagline')),
            'phone' => $g('site.phone', config('folks.phone')),
            'email' => $g('site.email', config('folks.email')),
            'whatsapp' => $g('site.whatsapp', config('folks.whatsapp')),
            'address' => $g('site.address', config('folks.address')),
            'city' => $g('site.city', config('folks.city')),
            'hours' => $g('site.hours', config('folks.hours')),
            'established' => $g('site.established', config('folks.established')),
            'hero_title' => $g('site.hero_title', config('folks.hero.title')),
            'hero_subtitle' => $g('site.hero_subtitle', config('folks.hero.subtitle')),
            'about' => $g('site.about', config('folks.about')),
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
                ['value' => '15+', 'label' => 'Years of Training'],
                ['value' => '5000+', 'label' => 'Students Trained'],
                ['value' => '100%', 'label' => 'Practical Focus'],
                ['value' => '4+', 'label' => 'Programme Options'],
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
        Setting::setValue('site.phone', $data['phone'] ?? '', 'contact');
        Setting::setValue('site.email', $data['email'] ?? '', 'contact');
        Setting::setValue('site.whatsapp', $data['whatsapp'] ?? '', 'contact');
        Setting::setValue('site.address', $data['address'] ?? '', 'contact');
        Setting::setValue('site.city', $data['city'] ?? '', 'contact');
        Setting::setValue('site.hours', $data['hours'] ?? '', 'contact');
        Setting::setValue('site.established', $data['established'] ?? '', 'general');
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
}
