<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SiteContentService;
use Illuminate\Database\Seeder;

class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(SiteContentService::class);

        if (Setting::query()->where('key', 'site.name')->exists()) {
            return;
        }

        $service->syncDefaultsFromConfig();
    }
}
