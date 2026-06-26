<?php

namespace App\Services;

use App\Enums\AdmissionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\BatchStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CrmDashboardService
{
    protected const STATS_CACHE_SECONDS = 60;

    protected const CHART_CACHE_SECONDS = 120;
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
     *     active_batches: int,
     *     attendance_present_today: int,
     *     attendance_marked_today: int,
     *     attendance_students_in_batches: int
     * }
     */
    public function stats(): array
    {
        return Cache::remember('crm.dashboard.stats', self::STATS_CACHE_SECONDS, function (): array {
            $today = today();
            $monthStart = now()->startOfMonth();
            $attendance = $this->todayAttendanceTotals($today);

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
                'pending_fees_total' => (float) FeeStructure::query()->forActiveEnrollments()->sum('pending_amount'),
                'active_batches' => Batch::query()
                    ->where('status', BatchStatus::Active)
                    ->count(),
                'attendance_present_today' => $attendance['present'],
                'attendance_marked_today' => $attendance['marked'],
                'attendance_students_in_batches' => $attendance['students_in_batches'],
            ];
        });
    }

    /**
     * @return array{
     *     date_label: string,
     *     rows: list<array{
     *         id: int,
     *         label: string,
     *         students: int,
     *         present_today: int,
     *         absent_today: int,
     *         leave_today: int,
     *         marked_today: int,
     *         not_marked_today: int,
     *         pending_fees: float
     *     }>,
     *     totals: array{
     *         students: int,
     *         present_today: int,
     *         absent_today: int,
     *         leave_today: int,
     *         marked_today: int,
     *         not_marked_today: int,
     *         pending_fees: float
     *     }
     * }
     */
    public function batchOverview(): array
    {
        return Cache::remember('crm.dashboard.batch_overview', self::STATS_CACHE_SECONDS, function (): array {
            $today = today();

            $batches = Batch::query()
                ->where('status', BatchStatus::Active)
                ->with('course')
                ->orderBy('name')
                ->get();

            if ($batches->isEmpty()) {
                return [
                    'date_label' => $today->format('d M Y'),
                    'rows' => [],
                    'totals' => [
                        'students' => 0,
                        'present_today' => 0,
                        'absent_today' => 0,
                        'leave_today' => 0,
                        'marked_today' => 0,
                        'not_marked_today' => 0,
                        'pending_fees' => 0.0,
                    ],
                ];
            }

            $batchIds = $batches->pluck('id');

            $studentCounts = BatchStudent::query()
                ->whereIn('batch_id', $batchIds)
                ->where('is_active', true)
                ->selectRaw('batch_id, COUNT(*) as total')
                ->groupBy('batch_id')
                ->pluck('total', 'batch_id');

            $attendanceByBatch = Attendance::query()
                ->whereIn('batch_id', $batchIds)
                ->whereDate('attendance_date', $today)
                ->get()
                ->groupBy('batch_id');

            $studentsByBatch = BatchStudent::query()
                ->whereIn('batch_id', $batchIds)
                ->where('is_active', true)
                ->get(['batch_id', 'student_id'])
                ->groupBy('batch_id');

            $studentIds = $studentsByBatch->flatten(1)->pluck('student_id')->unique()->values();

            $pendingByStudent = $studentIds->isEmpty()
                ? collect()
                : FeeStructure::query()
                    ->forActiveEnrollments()
                    ->whereHas('enrollment', fn ($query) => $query->whereIn('student_id', $studentIds))
                    ->with('enrollment:id,student_id')
                    ->get(['id', 'enrollment_id', 'pending_amount'])
                    ->groupBy(fn (FeeStructure $row): int => (int) $row->enrollment->student_id)
                    ->map(fn ($group): float => round((float) $group->sum('pending_amount'), 2));

            $rows = [];
            $totals = [
                'students' => 0,
                'present_today' => 0,
                'absent_today' => 0,
                'leave_today' => 0,
                'marked_today' => 0,
                'not_marked_today' => 0,
                'pending_fees' => 0.0,
            ];

            foreach ($batches as $batch) {
                $students = (int) ($studentCounts[$batch->id] ?? 0);
                $markedRows = $attendanceByBatch->get($batch->id, collect());
                $present = $markedRows->where('status', AttendanceStatus::Present)->count();
                $absent = $markedRows->where('status', AttendanceStatus::Absent)->count();
                $leave = $markedRows->where('status', AttendanceStatus::Leave)->count();
                $marked = $markedRows->count();
                $notMarked = max(0, $students - $marked);

                $batchStudentIds = $studentsByBatch->get($batch->id, collect())->pluck('student_id');
                $pendingFees = round((float) $batchStudentIds->sum(
                    fn (int $studentId): float => (float) ($pendingByStudent[$studentId] ?? 0),
                ), 2);

                $rows[] = [
                    'id' => $batch->id,
                    'label' => $batch->selectLabel(),
                    'students' => $students,
                    'present_today' => $present,
                    'absent_today' => $absent,
                    'leave_today' => $leave,
                    'marked_today' => $marked,
                    'not_marked_today' => $notMarked,
                    'pending_fees' => $pendingFees,
                ];

                $totals['students'] += $students;
                $totals['present_today'] += $present;
                $totals['absent_today'] += $absent;
                $totals['leave_today'] += $leave;
                $totals['marked_today'] += $marked;
                $totals['not_marked_today'] += $notMarked;
                $totals['pending_fees'] = round($totals['pending_fees'] + $pendingFees, 2);
            }

            return [
                'date_label' => $today->format('d M Y'),
                'rows' => $rows,
                'totals' => $totals,
            ];
        });
    }

    /**
     * @return array{present: int, marked: int, students_in_batches: int}
     */
    protected function todayAttendanceTotals(Carbon $today): array
    {
        $batchIds = Batch::query()
            ->where('status', BatchStatus::Active)
            ->pluck('id');

        if ($batchIds->isEmpty()) {
            return ['present' => 0, 'marked' => 0, 'students_in_batches' => 0];
        }

        $studentsInBatches = (int) BatchStudent::query()
            ->whereIn('batch_id', $batchIds)
            ->where('is_active', true)
            ->count();

        $markedRows = Attendance::query()
            ->whereIn('batch_id', $batchIds)
            ->whereDate('attendance_date', $today)
            ->get();

        return [
            'present' => $markedRows->where('status', AttendanceStatus::Present)->count(),
            'marked' => $markedRows->count(),
            'students_in_batches' => $studentsInBatches,
        ];
    }

    public static function flushStatsCache(): void
    {
        Cache::forget('crm.dashboard.stats');
    }

    public static function flushAllCaches(): void
    {
        self::flushStatsCache();

        foreach ([5, 6, 7, 8, 9, 10, 11, 12] as $months) {
            Cache::forget("crm.dashboard.monthly_admissions.{$months}");
            Cache::forget("crm.dashboard.monthly_fees.{$months}");
        }

        Cache::forget('crm.dashboard.lead_sources');
        Cache::forget('crm.dashboard.course_admissions');
        Cache::forget('crm.dashboard.batch_overview');

        foreach ([5, 10, 15, 20] as $limit) {
            Cache::forget("crm.dashboard.recent_enquiries.{$limit}");
            Cache::forget("crm.dashboard.pending_admissions.{$limit}");
        }
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function monthlyAdmissions(int $months = 6): array
    {
        return Cache::remember("crm.dashboard.monthly_admissions.{$months}", self::CHART_CACHE_SECONDS, function () use ($months): array {
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
        });
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, float>}
     */
    public function monthlyFeeCollection(int $months = 6): array
    {
        return Cache::remember("crm.dashboard.monthly_fees.{$months}", self::CHART_CACHE_SECONDS, function () use ($months): array {
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
        });
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function leadSourceBreakdown(): array
    {
        return Cache::remember('crm.dashboard.lead_sources', self::CHART_CACHE_SECONDS, function (): array {
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
        });
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function courseWiseAdmissions(): array
    {
        return Cache::remember('crm.dashboard.course_admissions', self::CHART_CACHE_SECONDS, function (): array {
            $rows = Admission::query()
                ->where('admissions.status', AdmissionStatus::Approved)
                ->join('enquiries', 'admissions.enquiry_id', '=', 'enquiries.id')
                ->join('courses', 'enquiries.course_id', '=', 'courses.id')
                ->selectRaw('courses.name as course_name, COUNT(*) as total')
                ->groupBy('courses.name')
                ->orderByDesc('total')
                ->limit(8)
                ->pluck('total', 'course_name');

            return [
                'labels' => $rows->keys()->all(),
                'data' => $rows->map(fn ($total): int => (int) $total)->values()->all(),
            ];
        });
    }

    /**
     * @return Collection<int, Enquiry>
     */
    public function recentEnquiries(int $limit = 5): Collection
    {
        return Cache::remember("crm.dashboard.recent_enquiries.{$limit}", self::STATS_CACHE_SECONDS, function () use ($limit): Collection {
            return Enquiry::query()
                ->with(['student', 'course'])
                ->latest()
                ->limit($limit)
                ->get();
        });
    }

    /**
     * @return Collection<int, Admission>
     */
    public function pendingAdmissions(int $limit = 5): Collection
    {
        return Cache::remember("crm.dashboard.pending_admissions.{$limit}", self::STATS_CACHE_SECONDS, function () use ($limit): Collection {
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
        });
    }
}
