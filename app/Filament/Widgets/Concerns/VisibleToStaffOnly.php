<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\RoleName;
use App\Support\CrmAccess;
use Illuminate\Support\Facades\Auth;

trait VisibleToStaffOnly
{
    public static function canView(): bool
    {
        $user = Auth::user();

        if (! CrmAccess::hasPanelAccess($user)) {
            return false;
        }

        return ! $user->hasRole(RoleName::SuperAdmin->value);
    }
}
