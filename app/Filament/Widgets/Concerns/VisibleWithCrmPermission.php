<?php

namespace App\Filament\Widgets\Concerns;

use App\Enums\CrmPermission;
use App\Support\CrmAccess;
use Illuminate\Support\Facades\Auth;

trait VisibleWithCrmPermission
{
    abstract protected static function crmPermissionForWidget(): CrmPermission;

    public static function canView(): bool
    {
        return CrmAccess::can(Auth::user(), static::crmPermissionForWidget());
    }
}
