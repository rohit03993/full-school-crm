<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Wipe and reseed everything: php artisan migrate:fresh --seed
     */
    public function run(): void
    {
        $this->call([
            DemoBaselineSeeder::class,
        ]);

        if (config('institute.seed_demo_data', false)) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
