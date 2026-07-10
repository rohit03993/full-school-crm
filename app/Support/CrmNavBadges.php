<?php

namespace App\Support;

use App\Enums\AdmissionStatus;
use App\Models\Admission;
use App\Models\User;
use App\Services\FollowUpWorklistService;
use App\Services\MyLeadsService;
use App\Services\StudentCaseService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class CrmNavBadges
{
    protected const CACHE_SECONDS = 60;

    public static function myLeadsUncalled(?User $staff = null): int
    {
        $staff ??= Auth::user();

        if (! $staff) {
            return 0;
        }

        return app(MyLeadsService::class)->stats($staff)['uncalled'];
    }

    public static function followUpsDue(): int
    {
        return app(FollowUpWorklistService::class)->totalDueCount();
    }

    public static function admissionsPendingAction(): int
    {
        return (int) Cache::remember(
            'crm.nav.admissions_pending_action',
            self::CACHE_SECONDS,
            fn (): int => Admission::query()
                ->whereIn('status', [
                    AdmissionStatus::Submitted,
                    AdmissionStatus::VerificationPending,
                ])
                ->count(),
        );
    }

    /** Admissions ready for Super Admin to approve (matches Verification Pending filter). */
    public static function admissionsAwaitingApproval(): int
    {
        return (int) Cache::remember(
            'crm.nav.admissions_awaiting_approval',
            self::CACHE_SECONDS,
            fn (): int => Admission::query()
                ->where('status', AdmissionStatus::VerificationPending)
                ->count(),
        );
    }

    /** @deprecated Use admissionsPendingAction() — matches dashboard pending count. */
    public static function admissionsPendingVerification(): int
    {
        return self::admissionsPendingAction();
    }

    public static function flushAdmissionBadgeCache(): void
    {
        Cache::forget('crm.nav.admissions_pending_action');
        Cache::forget('crm.nav.admissions_awaiting_approval');
        Cache::forget('crm.nav.admissions_pending_verification');
    }

    public static function myMeetingsOpen(?User $staff = null): int
    {
        $staff ??= Auth::user();

        if (! $staff) {
            return 0;
        }

        return (int) Cache::remember(
            'crm.nav.my_meetings_open.'.$staff->id,
            self::CACHE_SECONDS,
            fn (): int => app(\App\Services\VisitMeetingAssignmentService::class)->openCountForStaff($staff),
        );
    }

    public static function flushMeetingBadgeCache(?int $staffUserId = null): void
    {
        if ($staffUserId !== null) {
            Cache::forget('crm.nav.my_meetings_open.'.$staffUserId);

            return;
        }

        $userId = Auth::id();

        if ($userId) {
            Cache::forget('crm.nav.my_meetings_open.'.$userId);
        }
    }

    public static function miscChargeAdjustmentsPending(): int
    {
        return (int) Cache::remember(
            'crm.nav.misc_charge_adjustments_pending',
            self::CACHE_SECONDS,
            fn (): int => app(\App\Services\FeeMiscChargeAdjustmentService::class)->pendingCount(),
        );
    }

    public static function flushMiscChargeAdjustmentBadgeCache(): void
    {
        Cache::forget('crm.nav.misc_charge_adjustments_pending');
    }

    public static function myCasesOpen(?User $staff = null): int
    {
        $staff ??= Auth::user();

        if (! $staff) {
            return 0;
        }

        return (int) Cache::remember(
            'crm.nav.my_cases_open.'.$staff->id,
            self::CACHE_SECONDS,
            fn (): int => app(StudentCaseService::class)->openCountForAssignee($staff),
        );
    }

    public static function allCasesOpen(): int
    {
        return (int) Cache::remember(
            'crm.nav.all_cases_open',
            self::CACHE_SECONDS,
            fn (): int => \App\Models\StudentCase::query()
                ->where('status', \App\Enums\StudentCaseStatus::Open)
                ->count(),
        );
    }

    public static function flushCaseBadgeCache(?int ...$staffUserIds): void
    {
        foreach (array_filter($staffUserIds) as $staffUserId) {
            Cache::forget('crm.nav.my_cases_open.'.$staffUserId);
        }

        Cache::forget('crm.nav.all_cases_open');
    }
}
