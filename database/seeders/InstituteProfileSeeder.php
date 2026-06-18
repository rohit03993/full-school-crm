<?php

namespace Database\Seeders;

use App\Enums\InstituteType;
use App\Support\InstituteProfile;
use Illuminate\Database\Seeder;

class InstituteProfileSeeder extends Seeder
{
    public function run(): void
    {
        \App\Models\Setting::query()->where('key', InstituteProfile::SETTING_KEY)->delete();

        $type = InstituteType::tryFrom((string) config('institute.type', InstituteType::School->value))
            ?? InstituteType::School;

        InstituteProfile::setType($type);
    }
}
