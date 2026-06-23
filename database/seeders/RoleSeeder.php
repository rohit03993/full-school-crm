<?php

namespace Database\Seeders;

use App\Services\CrmPermissionSyncService;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        app(CrmPermissionSyncService::class)->sync();

        $this->call(AdminRoleAssignSeeder::class);
    }
}
