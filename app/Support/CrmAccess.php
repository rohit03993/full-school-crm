<?php

namespace App\Support;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
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

        try {
            return $user->hasPermissionTo($permission);
        } catch (\Spatie\Permission\Exceptions\PermissionDoesNotExist) {
            return false;
        }
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
     * Queue exam-marks WhatsApp from the mark sheet. Coordinators publish marks;
     * messaging staff already have bulk campaigns. Teachers who only enter marks cannot send.
     */
    public static function canSendExamMarksWhatsApp(?User $user): bool
    {
        return self::canAny(
            $user,
            CrmPermission::MarksPublish,
            CrmPermission::WhatsappCampaigns,
        );
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
     * See the student Calls tab, last-call box, and call rows on the activity timeline.
     * Counsellor and Admission officer have this. Teacher / Faculty does not.
     */
    public static function canViewCallLog(?User $user): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Calls)) {
            return false;
        }

        return self::can($user, CrmPermission::LeadsCall);
    }

    /**
     * See student/parent mobile numbers and use Dial / tel: / call-bar.
     * Super Admin always can. Other staff need the direct staff checkbox, not a job role.
     */
    public static function canViewStudentMobile(?User $user): bool
    {
        return self::can($user, CrmPermission::StudentsViewMobile);
    }

    /**
     * Direct grant only. Job roles do not include this permission.
     */
    public static function hasDirectStudentMobileVisibility(User $user): bool
    {
        return $user->getDirectPermissions()
            ->contains('name', CrmPermission::StudentsViewMobile->value);
    }

    /**
     * Turn the staff "see student mobile" checkbox on or off.
     * Stored on the user, so role sync does not add or remove it.
     */
    public static function setStudentMobileVisibility(User $user, bool $allowed): void
    {
        $name = CrmPermission::StudentsViewMobile->value;

        \Spatie\Permission\Models\Permission::findOrCreate($name, 'web');

        if ($allowed) {
            if (! $user->hasDirectPermission($name)) {
                $user->givePermissionTo($name);
            }

            return;
        }

        if ($user->hasDirectPermission($name)) {
            $user->revokePermissionTo($name);
        }
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
