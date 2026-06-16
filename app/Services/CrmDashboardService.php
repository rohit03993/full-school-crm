<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\BatchStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Models\Admission;
use App\Models\Batch;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CrmDashboardService
{
    /**
     * @return array{
     *     total_enquiries: int,
     *     today_enquiries: int,
     *     website_today: int,
     *     walk_in_today: int,
     *     admissions_this_month: int,
     *     pending_admissions: int,
     *     active_students: int,
     *     fee_collection_today: float,
     *     pending_fees_total: float,
     *     active_batches: int
     * }
     */
    public function stats(): array
    {
        $today = today();
        $monthStart = now()->startOfMonth();

        return [
            'total_enquiries' => Enquiry::query()->count(),
            'today_enquiries' => Enquiry::query()->whereDate('created_at', $today)->count(),
            'website_today' => Enquiry::query()
                ->whereDate('created_at', $today)
                ->where('lead_source', LeadSource::Website)
                ->count(),
            'walk_in_today' => Enquiry::query()
                ->whereDate('created_at', $today)
                ->where('lead_source', LeadSource::WalkIn)
                ->count(),
            'admissions_this_month' => Admission::query()
                ->where('status', AdmissionStatus::Approved)
                ->where('approved_at', '>=', $monthStart)
                ->count(),
            'pending_admissions' => Admission::query()
                ->whereIn('status', [
                    AdmissionStatus::Submitted,
                    AdmissionStatus::VerificationPending,
                ])
                ->count(),
            'active_students' => Enrollment::query()
                ->where('is_active', true)
                ->where('status', EnrollmentStatus::Enrolled)
                ->count(),
            'fee_collection_today' => (float) Payment::query()
                ->whereDate('payment_date', $today)
                ->sum('amount'),
            'pending_fees_total' => (float) FeeStructure::query()->sum('pending_amount'),
            'active_batches' => Batch::query()
                ->where('status', BatchStatus::Active)
                ->whereDate('end_date', '>=', $today)
                ->count(),
        ];
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function monthlyAdmissions(int $months = 6): array
    {
        $labels = [];
        $data = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->format('M Y');
            $data[] = Admission::query()
                ->where('status', AdmissionStatus::Approved)
                ->whereYear('approved_at', $month->year)
                ->whereMonth('approved_at', $month->month)
                ->count();
        }

        return compact('labels', 'data');
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, float>}
     */
    public function monthlyFeeCollection(int $months = 6): array
    {
        $labels = [];
        $data = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $labels[] = $month->format('M Y');
            $data[] = (float) Payment::query()
                ->whereYear('payment_date', $month->year)
                ->whereMonth('payment_date', $month->month)
                ->sum('amount');
        }

        return compact('labels', 'data');
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function leadSourceBreakdown(): array
    {
        $counts = Enquiry::query()
            ->selectRaw('lead_source, COUNT(*) as total')
            ->groupBy('lead_source')
            ->pluck('total', 'lead_source');

        $labels = [];
        $data = [];

        foreach (LeadSource::cases() as $source) {
            $total = (int) ($counts[$source->value] ?? 0);

            if ($total === 0) {
                continue;
            }

            $labels[] = $source->label();
            $data[] = $total;
        }

        return compact('labels', 'data');
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function courseWiseAdmissions(): array
    {
        $rows = Admission::query()
            ->where('status', AdmissionStatus::Approved)
            ->with('enquiry.course')
            ->get()
            ->groupBy(fn (Admission $a) => $a->enquiry?->course?->name ?? 'Unknown')
            ->map(fn (Collection $items): int => $items->count())
            ->sortDesc()
            ->take(8);

        return [
            'labels' => $rows->keys()->all(),
            'data' => $rows->values()->all(),
        ];
    }

    /**
     * @return Collection<int, Enquiry>
     */
    public function recentEnquiries(int $limit = 5): Collection
    {
        return Enquiry::query()
            ->with(['student', 'course'])
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, Admission>
     */
    public function pendingAdmissions(int $limit = 5): Collection
    {
        return Admission::query()
            ->whereIn('status', [
                AdmissionStatus::Submitted,
                AdmissionStatus::VerificationPending,
            ])
            ->with(['student', 'enquiry.course'])
            ->orderByDesc('submitted_at')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
