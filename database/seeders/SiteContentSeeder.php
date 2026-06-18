<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'site.name' => config('institute.name'),
            'site.tagline' => config('institute.tagline'),
            'site.phone' => config('institute.phone'),
            'site.email' => config('institute.email'),
            'site.whatsapp' => config('institute.whatsapp'),
            'site.address' => config('institute.address'),
            'site.city' => config('institute.city'),
            'site.hours' => config('institute.hours'),
            'site.established' => config('institute.established'),
            'site.hero_title' => config('institute.hero.title'),
            'site.hero_subtitle' => config('institute.hero.subtitle'),
            'site.about' => config('institute.about'),
            'site.highlights' => [
                ['value' => '15+', 'label' => 'Years of Excellence'],
                ['value' => '5000+', 'label' => 'Students Enrolled'],
                ['value' => '100%', 'label' => 'Dedicated Faculty'],
                ['value' => '10+', 'label' => 'Programme Options'],
            ],
        ];

        foreach ($defaults as $key => $value) {
            if (Setting::query()->where('key', $key)->doesntExist()) {
                Setting::setValue($key, $value);
            }
        }
    }
}
