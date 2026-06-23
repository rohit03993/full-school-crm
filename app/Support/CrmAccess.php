<?php

namespace App\Support;

use App\Enums\CrmPermission;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Models\User;

class CrmAccess
{
    public static function can(?User $user, CrmPermission|string $permission): bool
    {
        if (! $user?->is_active) {
            return false;
        }

        $permission = $permission instanceof CrmPermission ? $permission->value : $permission;

        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return true;
        }

        return $user->hasPermissionTo($permission);
    }

    public static function canAny(?User $user, CrmPermission|string ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::can($user, $permission)) {
                return true;
            }
        }

        return false;
    }

    public static function hasPanelAccess(?User $user): bool
    {
        if (! $user?->is_active) {
            return false;
        }

        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return true;
        }

        if ($user->hasRole(RoleName::Staff->value)) {
            return true;
        }

        return $user->hasAnyRole(StaffJobRole::values());
    }

    /**
     * @return list<string>
     */
    public static function jobRoleNamesFor(User $user): array
    {
        return $user->roles
            ->pluck('name')
            ->filter(fn (string $name): bool => StaffJobRole::tryFrom($name) !== null)
            ->values()
            ->all();
    }
}
