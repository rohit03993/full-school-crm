<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->isStaff($user);
    }

    public function create(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function update(User $user, Payment $payment): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function delete(User $user, Payment $payment): bool
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
