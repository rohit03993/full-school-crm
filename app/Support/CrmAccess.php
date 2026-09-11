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

    /**
     * Super Admin, accountant (collect / finance stats), or anyone with fee-structure access.
     */
    public static function canViewFees(?User $user): bool
    {
        return self::canAny(
            $user,
            CrmPermission::FeesCollect,
            CrmPermission::FeesAdjustStructure,
            CrmPermission::DashboardFinanceStats,
        );
    }

    /**
     * See student/parent mobile numbers and use Dial / tel: / call-bar.
     */
    public static function canViewStudentMobile(?User $user): bool
    {
        return self::can($user, CrmPermission::StudentsViewMobile);
    }

    /**
     * Visible label for a student mobile. When denied: "Hidden" (never the real digits).
     */
    public static function studentMobileLabel(?User $user, ?string $mobile): string
    {
        if (! filled($mobile)) {
            return '—';
        }

        return self::canViewStudentMobile($user) ? (string) $mobile : 'Hidden';
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
