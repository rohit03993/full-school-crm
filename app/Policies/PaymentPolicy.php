<?php

namespace App\Policies;

use App\Enums\CrmPermission;
use App\Models\Payment;
use App\Models\User;
use App\Support\CrmAccess;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return CrmAccess::can($user, CrmPermission::StudentsView);
    }

    public function view(User $user, Payment $payment): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return CrmAccess::can($user, CrmPermission::FeesCollect);
    }

    public function update(User $user, Payment $payment): bool
    {
        return CrmAccess::can($user, CrmPermission::FeesAdjustStructure);
    }

    public function delete(User $user, Payment $payment): bool
    {
        return CrmAccess::can($user, CrmPermission::FeesAdjustStructure);
    }
}
