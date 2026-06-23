<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Core reference data for a fresh CRM (roles, admin, session, courses, exam types).
 * Does not create sample students or leads — use DemoDataSeeder for that.
 */
class DemoBaselineSeeder extends Seeder
{
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
        ]);
    }
}
