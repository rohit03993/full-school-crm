<?php

namespace App\Policies;

use App\Enums\CrmPermission;
use App\Enums\ReportType;
use App\Models\User;
use App\Support\CrmAccess;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return CrmAccess::can($user, CrmPermission::ReportsView);
    }

    public function export(User $user, ReportType $report): bool
    {
        if (! CrmAccess::can($user, CrmPermission::ReportsExport)) {
            return false;
        }

        if ($report->isFinancial()) {
            return CrmAccess::can($user, CrmPermission::DashboardFinanceStats);
        }

        return true;
    }
}
