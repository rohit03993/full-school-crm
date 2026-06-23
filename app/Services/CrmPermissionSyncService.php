<?php

namespace App\Services;

use App\Enums\CrmPermission;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Support\StaffRolePermissions;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class CrmPermissionSyncService
{
    public function sync(): array
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $createdPermissions = 0;

        foreach (CrmPermission::cases() as $permission) {
            Permission::query()->firstOrCreate(
                ['name' => $permission->value, 'guard_name' => 'web'],
            );
            $createdPermissions++;
        }

        foreach (StaffJobRole::cases() as $jobRole) {
            $role = Role::query()->firstOrCreate(
                ['name' => $jobRole->value, 'guard_name' => 'web'],
            );

            $role->syncPermissions(StaffRolePermissions::permissionNamesForRole($jobRole));
        }

        $legacyStaff = Role::query()->firstOrCreate(
            ['name' => RoleName::Staff->value, 'guard_name' => 'web'],
        );

        $legacyStaff->syncPermissions(array_map(
            fn (CrmPermission $permission): string => $permission->value,
            StaffRolePermissions::legacyStaffPermissions(),
        ));

        $superAdmin = Role::query()->firstOrCreate(
            ['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web'],
        );

        $superAdmin->syncPermissions(CrmPermission::values());

        foreach (RoleName::cases() as $roleName) {
            Role::query()->firstOrCreate(
                ['name' => $roleName->value, 'guard_name' => 'web'],
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return [
            'permissions' => count(CrmPermission::cases()),
            'job_roles' => count(StaffJobRole::cases()),
        ];
    }
}
