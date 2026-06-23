<?php

namespace App\Policies;

use App\Enums\CrmPermission;
use App\Models\FeeStructure;
use App\Models\User;
use App\Support\CrmAccess;

class FeeStructurePolicy
{
    public function viewAny(User $user): bool
    {
        return CrmAccess::can($user, CrmPermission::StudentsView);
    }

    public function view(User $user, FeeStructure $feeStructure): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, FeeStructure $feeStructure): bool
    {
        return CrmAccess::can($user, CrmPermission::FeesAdjustStructure);
    }
}
