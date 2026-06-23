<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminRoleAssignSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('mobile', env('ADMIN_MOBILE', '9876543210'))->first();

        if ($admin) {
            $admin->update(['is_active' => true]);
            $admin->syncRoles([RoleName::SuperAdmin->value]);
        }
    }
}
