<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\FeeStructure;
use App\Models\User;

class FeeStructurePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, FeeStructure $feeStructure): bool
    {
        return $this->isStaff($user);
    }

    public function update(User $user, FeeStructure $feeStructure): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    protected function isStaff(User $user): bool
    {
        return $user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Staff->value,
        ]);
    }
}
