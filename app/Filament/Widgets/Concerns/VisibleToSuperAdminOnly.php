<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\CrmPermission;
use App\Support\CrmAccess;
use Illuminate\Support\Facades\Auth;

trait VisibleToSuperAdminOnly
{
    public static function canView(): bool
    {
        return CrmAccess::can(Auth::user(), CrmPermission::DashboardOwnerStats);
    }
}
