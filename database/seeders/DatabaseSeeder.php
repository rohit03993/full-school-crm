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
            RoleSeeder::class,
            AdminUserSeeder::class,
            AcademicSessionSeeder::class,
            InstituteProfileSeeder::class,
            CourseSeeder::class,
            ActivityTypeSeeder::class,
            SiteContentSeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
