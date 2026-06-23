<?php

namespace App\Support;

use App\Enums\AdmissionStatus;
use App\Models\Admission;
use App\Models\User;
use App\Services\FollowUpWorklistService;
use App\Services\MyLeadsService;
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
}
