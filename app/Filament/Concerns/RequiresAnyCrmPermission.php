<?php

namespace App\Filament\Concerns;

use App\Enums\CrmPermission;
use App\Support\CrmAccess;
use Illuminate\Support\Facades\Auth;

trait RequiresAnyCrmPermission
{
    /**
     * @return list<CrmPermission>
     */
    abstract protected static function anyCrmPermissions(): array;

    public static function canAccess(): bool
    {
        return CrmAccess::canAny(Auth::user(), ...static::anyCrmPermissions());
    }
}
