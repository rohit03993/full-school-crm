<?php

namespace App\Policies;

use App\Enums\ReportType;
use App\Enums\RoleName;
use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function export(User $user, ReportType $report): bool
    {
        if (! $this->isStaff($user)) {
            return false;
        }

        if ($report->isFinancial()) {
            return $user->hasRole(RoleName::SuperAdmin->value);
        }

        return true;
    }

    protected function isStaff(User $user): bool
    {
        return $user->hasAnyRole([
            RoleName::SuperAdmin->value,
            RoleName::Staff->value,
        ]);
    }
}
