<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Models\User;

class SystemUser
{
    public static function id(): int
    {
        $userId = User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($query) => $query->where('name', RoleName::SuperAdmin->value))
            ->value('id');

        return (int) ($userId ?? User::query()->value('id') ?? 1);
    }

    public static function resolve(): User
    {
        return User::query()->findOrFail(self::id());
    }
}
