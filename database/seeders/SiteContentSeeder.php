<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'site.name' => config('folks.name'),
            'site.tagline' => config('folks.tagline'),
            'site.phone' => config('folks.phone'),
            'site.email' => config('folks.email'),
            'site.whatsapp' => config('folks.whatsapp'),
            'site.address' => config('folks.address'),
            'site.city' => config('folks.city'),
            'site.hours' => config('folks.hours'),
            'site.established' => config('folks.established'),
            'site.hero_title' => config('folks.hero.title'),
            'site.hero_subtitle' => config('folks.hero.subtitle'),
            'site.about' => config('folks.about'),
            'site.highlights' => [
                ['value' => '15+', 'label' => 'Years of Training'],
                ['value' => '5000+', 'label' => 'Students Trained'],
                ['value' => '100%', 'label' => 'Practical Focus'],
                ['value' => '4+', 'label' => 'Programme Options'],
            ],
        ];

        foreach ($defaults as $key => $value) {
            if (Setting::query()->where('key', $key)->doesntExist()) {
                Setting::setValue($key, $value);
            }
        }
    }
}
