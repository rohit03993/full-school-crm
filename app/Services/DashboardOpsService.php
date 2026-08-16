<?php

namespace App\Services;

use App\Enums\CallStatus;
use App\Enums\CrmPermission;
use App\Enums\HomeworkAssignmentStatus;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Models\Enquiry;
use App\Models\HomeworkAssignment;
use App\Models\Payment;
use App\Models\StudentCall;
use App\Models\User;
use App\Support\CrmAccess;
use App\Support\CrmNavBadges;
use App\Support\DashboardFilters;
use App\Support\FeatureGate;
use Illuminate\Support\Facades\Cache;

/**
 * Action-first dashboard numbers: what needs attention, and today's pulse.
 */
class DashboardOpsService
{
    protected const CACHE_SECONDS = 60;

    /**
     * @return array{
     *     approvals_total: int,
     *     admissions_pending: int,
     *     fee_adjustments_pending: int,
     *     homework_awaiting_approve: int,
     *     follow_ups_due: int,
     *     uncalled_leads: int,
     *     open_cases: int,
     *     open_meetings: int,
     *     staff_work_total: int,
     *     attendance_marked: int,
     *     attendance_expected: int,
     *     attendance_unmarked: int,
     *     attendance_coverage_pct: int,
     *     queue_count: int,
     *     overdue_students: int
     * }
     */
    public function attentionSnapshot(?DashboardFilters $filters, User $user): array
    {
        $filters ??= DashboardFilters::default();
        $isOwner = $user->hasRole(RoleName::SuperAdmin->value);

        return $this->remember(
            $filters->cacheKey('ops_attention.'.$user->id.($isOwner ? '.owner' : '.staff')),
            function () use ($filters, $user, $isOwner): array {
                $stats = app(CrmDashboardService::class)->stats($filters);
                $admissions = $isOwner ? CrmNavBadges::admissionsPendingAction() : 0;
                $feeAdjustments = $isOwner && FeatureGate::enabled(LicenseFeature::Fees)
                    ? CrmNavBadges::miscChargeAdjustmentsPending()
                    : 0;
                $homeworkAwaiting = FeatureGate::enabled(LicenseFeature::Homework)
                    && ($isOwner || CrmAccess::can($user, CrmPermission::HomeworkManage))
                    ? $this->homeworkAwaitingApproveCount()
                    : 0;

                $followUps = FeatureGate::enabled(LicenseFeature::Enquiries)
                    ? CrmNavBadges::followUpsDue($user)
                    : 0;
                $uncalled = FeatureGate::enabled(LicenseFeature::Enquiries)
                    ? ($isOwner ? $this->instituteUncalledAssignedCount() : CrmNavBadges::myLeadsUncalled($user))
                    : 0;
                $openCases = FeatureGate::enabled(LicenseFeature::Cases)
                    ? ($isOwner ? CrmNavBadges::allCasesOpen() : CrmNavBadges::myCasesOpen($user))
                    : 0;
                $openMeetings = CrmNavBadges::myMeetingsOpen($user);

                $expected = (int) $stats['attendance_students_in_batches'];
                $marked = (int) $stats['attendance_marked_today'];
                $unmarked = max(0, $expected - $marked);

                $queue = 0;
                if (FeatureGate::enabled(LicenseFeature::Calls) && CrmAccess::can($user, CrmPermission::DashboardCallingStats)) {
                    $queue = (int) (app(CallQueueService::class)->todayStats($user)['queue_count'] ?? 0);
                }

                $overdueStudents = 0;
                if (FeatureGate::enabled(LicenseFeature::Fees) && CrmAccess::canViewFees($user)) {
                    $overdueStudents = (int) (app(CrmDashboardService::class)->feeSummary($filters)['overdue_students_count'] ?? 0);
                }

                $approvals = $admissions + $feeAdjustments + $homeworkAwaiting;
                $staffWork = $followUps + $uncalled + $openCases + $openMeetings;

                return [
                    'approvals_total' => $approvals,
                    'admissions_pending' => $admissions,
                    'fee_adjustments_pending' => $feeAdjustments,
                    'homework_awaiting_approve' => $homeworkAwaiting,
                    'follow_ups_due' => $followUps,
                    'uncalled_leads' => $uncalled,
                    'open_cases' => $openCases,
                    'open_meetings' => $openMeetings,
                    'staff_work_total' => $staffWork,
                    'attendance_marked' => $marked,
                    'attendance_expected' => $expected,
                    'attendance_unmarked' => $unmarked,
                    'attendance_coverage_pct' => $expected > 0 ? (int) round(($marked / $expected) * 100) : 0,
                    'queue_count' => $queue,
                    'overdue_students' => $overdueStudents,
                ];
            },
        );
    }

    /**
     * Calendar-today pulse (institute timezone). Filter session/course still scopes attendance via stats().
     *
     * @return array{
     *     leads_today: int,
     *     calls_today: int,
     *     calls_connected_today: int,
     *     visits_today: int,
     *     homework_given_today: int,
     *     whatsapp_sent_today: int,
     *     whatsapp_cost_today: float,
     *     fees_amount_today: float,
     *     fees_payments_today: int,
     *     as_of_label: string
     * }
     */
    public function todayPulse(?DashboardFilters $filters, User $user): array
    {
        $filters ??= DashboardFilters::default();
        $isOwner = $user->hasRole(RoleName::SuperAdmin->value);

        return $this->remember(
            $filters->cacheKey('ops_pulse.'.$user->id.($isOwner ? '.owner' : '.staff')),
            function () use ($filters, $user, $isOwner): array {
                $today = today();
                $stats = app(CrmDashboardService::class)->stats($filters);

                $callsToday = 0;
                $callsConnected = 0;
                if (FeatureGate::enabled(LicenseFeature::Calls)) {
                    if ($isOwner) {
                        $callsToday = StudentCall::query()->whereDate('called_at', $today)->count();
                        $callsConnected = StudentCall::query()
                            ->whereDate('called_at', $today)
                            ->where('call_status', CallStatus::Connected)
                            ->count();
                    } elseif (CrmAccess::can($user, CrmPermission::DashboardCallingStats)) {
                        $callStats = app(CallQueueService::class)->todayStats($user);
                        $callsToday = (int) $callStats['calls_today'];
                        $callsConnected = (int) $callStats['connected_today'];
                    }
                }

                $visitsToday = 0;
                if (FeatureGate::enabled(LicenseFeature::Enquiries)) {
                    $visitsToday = (int) (app(InstituteVisitsService::class)->stats($today->copy(), $today->copy())['total_visits'] ?? 0);
                }

                $homeworkGiven = 0;
                if (FeatureGate::enabled(LicenseFeature::Homework)) {
                    $homeworkGiven = $this->homeworkGivenCount($today, $today);
                }

                $waSent = 0;
                $waCost = 0.0;
                if (FeatureGate::enabled(LicenseFeature::WhatsApp)) {
                    $wa = app(WhatsAppAnalyticsService::class)->localOutboundSummary(
                        $today->copy()->startOfDay(),
                        $today->copy()->endOfDay(),
                    );
                    $waSent = (int) ($wa['total_messages'] ?? 0);
                    $waCost = (float) ($wa['total_cost_inr'] ?? 0);
                }

                $feesAmount = (float) ($stats['fee_collection_today'] ?? 0);
                $feesCount = 0;
                if (FeatureGate::enabled(LicenseFeature::Fees) && CrmAccess::canViewFees($user)) {
                    $feesCount = (int) Payment::query()->whereDate('payment_date', $today)->count();
                } else {
                    $feesAmount = 0.0;
                }

                return [
                    'leads_today' => FeatureGate::enabled(LicenseFeature::Enquiries)
                        ? (int) ($stats['today_enquiries'] ?? 0)
                        : 0,
                    'calls_today' => $callsToday,
                    'calls_connected_today' => $callsConnected,
                    'visits_today' => $visitsToday,
                    'homework_given_today' => $homeworkGiven,
                    'whatsapp_sent_today' => $waSent,
                    'whatsapp_cost_today' => $waCost,
                    'fees_amount_today' => $feesAmount,
                    'fees_payments_today' => $feesCount,
                    'as_of_label' => $today->format('d M Y'),
                ];
            },
        );
    }

    protected function homeworkAwaitingApproveCount(): int
    {
        return (int) HomeworkAssignment::query()
            ->where('status', HomeworkAssignmentStatus::Submitted)
            ->count();
    }

    protected function homeworkGivenCount(\Illuminate\Support\Carbon $from, \Illuminate\Support\Carbon $to): int
    {
        return (int) HomeworkAssignment::query()
            ->whereDate('homework_date', '>=', $from->toDateString())
            ->whereDate('homework_date', '<=', $to->toDateString())
            ->where(function ($query): void {
                $query->whereNotNull('published_at')
                    ->orWhereIn('status', [
                        HomeworkAssignmentStatus::Approved,
                        HomeworkAssignmentStatus::Sent,
                        HomeworkAssignmentStatus::Submitted,
                    ]);
            })
            ->count();
    }

    protected function instituteUncalledAssignedCount(): int
    {
        return (int) Enquiry::query()
            ->whereNotNull('calling_assigned_at')
            ->whereNotNull('meeting_with_user_id')
            ->whereHas('student', fn ($query) => $query->where('total_calls', 0))
            ->count();
    }

    protected function remember(string $key, callable $callback): mixed
    {
        $version = (int) Cache::get('crm.dashboard.version', 1);

        return Cache::remember($key.'.v'.$version, self::CACHE_SECONDS, $callback);
    }
}
