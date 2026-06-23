<?php

namespace App\Support;

use App\Services\CrmDashboardService;
use App\Services\FollowUpWorklistService;
use App\Services\MyLeadsService;

/**
 * Single entry point to refresh cached CRM counts after data changes.
 */
class CrmCacheInvalidator
{
    public static function afterEnquiryChange(?int $assignedStaffUserId = null): void
    {
        CrmDashboardService::flushAllCaches();

        if ($assignedStaffUserId !== null) {
            MyLeadsService::flushStatsCache($assignedStaffUserId);
        }
    }

    public static function afterAdmissionChange(?int $assignedStaffUserId = null): void
    {
        CrmDashboardService::flushAllCaches();
        CrmNavBadges::flushAdmissionBadgeCache();

        if ($assignedStaffUserId !== null) {
            MyLeadsService::flushStatsCache($assignedStaffUserId);
        }
    }

    public static function afterPayment(): void
    {
        CrmDashboardService::flushAllCaches();
    }

    public static function afterVisitOrFollowUpChange(?int $assignedStaffUserId = null): void
    {
        CrmDashboardService::flushAllCaches();
        FollowUpWorklistService::flushDueCountCache();

        if ($assignedStaffUserId !== null) {
            MyLeadsService::flushStatsCache($assignedStaffUserId);
        }
    }

    public static function afterCallLogged(int $staffUserId, ?int $assignedStaffUserId = null): void
    {
        CrmDashboardService::flushAllCaches();
        MyLeadsService::flushStatsCache($staffUserId);
        FollowUpWorklistService::flushDueCountCache();

        if ($assignedStaffUserId !== null && $assignedStaffUserId !== $staffUserId) {
            MyLeadsService::flushStatsCache($assignedStaffUserId);
        }
    }

    public static function afterBulkImport(): void
    {
        CrmDashboardService::flushAllCaches();
        CrmNavBadges::flushAdmissionBadgeCache();
    }
}
