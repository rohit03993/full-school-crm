<?php

namespace App\Policies;

use App\Enums\CrmPermission;
use App\Models\Admission;
use App\Models\User;
use App\Support\CrmAccess;

class AdmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return CrmAccess::canAny(
            $user,
            CrmPermission::AdmissionsView,
            CrmPermission::StudentsView,
        );
    }

    public function view(User $user, Admission $admission): bool
    {
        return $this->viewAny($user);
    }

    public function approve(User $user, Admission $admission): bool
    {
        return CrmAccess::can($user, CrmPermission::AdmissionsApprove)
            && $admission->canBeApproved();
    }

    public function returnForCorrection(User $user, Admission $admission): bool
    {
        return CrmAccess::can($user, CrmPermission::AdmissionsApprove)
            && $admission->canBeApproved();
    }
}
