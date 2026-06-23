<?php

namespace App\Filament\Concerns;

use App\Enums\CrmPermission;
use App\Support\CrmAccess;
use Illuminate\Support\Facades\Auth;

trait RequiresCrmPermission
{
    abstract protected static function requiredCrmPermission(): CrmPermission;

    public static function canAccess(): bool
    {
        return CrmAccess::can(Auth::user(), static::requiredCrmPermission());
    }
}
