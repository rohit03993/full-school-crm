<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $guard = 'web';

        foreach (RoleName::cases() as $roleName) {
            Role::query()->firstOrCreate(
                ['name' => $roleName->value, 'guard_name' => $guard],
            );
        }

        $admin = User::query()->where('email', 'rohit03993@gmail.com')->first();

        if ($admin) {
            $admin->update(['is_active' => true]);
            $admin->assignRole(RoleName::SuperAdmin->value);
        }
    }
}
