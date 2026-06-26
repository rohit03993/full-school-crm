<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Models\User;

final class StaffOptions
{
    /**
     * Active CRM users who can be assigned as batch faculty (super admin + all staff job roles).
     *
     * @return array<int, string>
     */
    public static function facultyOptions(): array
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->whereIn('name', array_merge(
                [RoleName::SuperAdmin->value],
                StaffJobRole::values(),
            )))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
