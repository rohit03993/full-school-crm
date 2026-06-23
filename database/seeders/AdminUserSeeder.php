<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $mobile = (string) env('ADMIN_MOBILE', '9876543210');
        $password = (string) env('ADMIN_PASSWORD', 'Admin@2026');
        $name = (string) env('ADMIN_NAME', 'Super Admin');

        $user = User::query()->updateOrCreate(
            ['mobile' => $mobile],
            [
                'name' => $name,
                'email' => null,
                'password' => $password,
                'is_active' => true,
            ],
        );

        Role::query()->firstOrCreate(
            ['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web'],
        );

        $user->syncRoles([RoleName::SuperAdmin->value]);

        $this->command?->info("Super Admin ready: {$mobile}");
    }
}
