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
use App\Support\DashboardFilters;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CrmDashboardService
{
    protected const STATS_CACHE_SECONDS = 60;

    protected const CHART_CACHE_SECONDS = 120;

    /**
     * Bumped instead of forgetting individual keys, because filtered dashboards
     * produce one cache entry per filter combination and the database cache
     * store does not support tags.
     */
    protected const VERSION_CACHE_KEY = 'crm.dashboard.version';

    /**
     * @return array{
     *     total_enquiries: int,
     *     today_enquiries: int,
     *     website_today: int,
     *     walk_in_today: int,
     *     range_enquiries: int,
     *     range_website: int,
     *     range_walk_in: int,
     *     admissions_this_month: int,
     *     range_admissions: int,
     *     pending_admissions: int,
     *     active_students: int,
     *     fee_collection_today: float,
     *     range_fee_collection: float,
     *     pending_fees_total: float,
     *     active_batches: int,
     *     attendance_present_today: int,
     *     attendance_marked_today: int,
     *     attendance_students_in_batches: int,
     *     range_label: string,
     *     as_of_label: string
     * }
     */
    public function stats(?DashboardFilters $filters = null): array
    {
        $filters ??= DashboardFilters::default();

        return $this->remember($filters->cacheKey('stats'), self::STATS_CACHE_SECONDS, function () use ($filters): array {
            $today = today();
            $monthStart = now()->startOfMonth();
            $from = $filters->from->toDateString();
            $to = $filters->to->toDateString();
            $attendance = $this->attendanceTotals($filters);

            $enquiriesInRange = fn (): Builder => Enquiry::query()
                ->whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to)
                ->when($filters->courseId, fn (Builder $query, int $courseId) => $query->where('course_id', $courseId));

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
                'range_enquiries' => $enquiriesInRange()->count(),
                'range_website' => $enquiriesInRange()->where('lead_source', LeadSource::Website)->count(),
                'range_walk_in' => $enquiriesInRange()->where('lead_source', LeadSource::WalkIn)->count(),
                'admissions_this_month' => Admission::query()
                    ->where('status', AdmissionStatus::Approved)
                    ->where('approved_at', '>=', $monthStart)
                    ->count(),
                'range_admissions' => Admission::query()
                    ->where('status', AdmissionStatus::Approved)
                    ->whereDate('approved_at', '>=', $from)
                    ->whereDate('approved_at', '<=', $to)
                    ->count(),
                'pending_admissions' => Admission::query()
                    ->whereIn('status', [
                        AdmissionStatus::Submitted,
                        AdmissionStatus::VerificationPending,
                    ])
                    ->count(),
                'active_students' => $this->activeEnrollmentsQuery($filters)->count(),
                'fee_collection_today' => (float) Payment::query()
                    ->whereDate('payment_date', $today)
                    ->sum('amount'),
                'range_fee_collection' => (float) Payment::query()
                    ->whereDate('payment_date', '>=', $from)
                    ->whereDate('payment_date', '<=', $to)
                    ->sum('amount'),
                'pending_fees_total' => $this->pendingFeesTotal($filters),
                'active_batches' => $this->batchQuery($filters)->count(),
                'attendance_present_today' => $attendance['present'],
                'attendance_marked_today' => $attendance['marked'],
                'attendance_students_in_batches' => $attendance['students_in_batches'],
                'range_label' => $filters->rangeLabel(),
                'as_of_label' => $filters->asOfLabel(),
            ];
        });
    }

    /**
     * @return array{
     *     collection_today: float,
     *     collection_month: float,
     *     pending_fees_total: float,
     *     pending_penalties_total: float,
     *     overdue_installment_count: int,
     *     overdue_students_count: int,
     *     overdue_amount: float
     * }
     */
    public function feeSummary(?DashboardFilters $filters = null): array
    {
        $filters ??= DashboardFilters::default();

        return $this->remember($filters->cacheKey('fee_summary'), self::STATS_CACHE_SECONDS, function () use ($filters): array {
            return app(FeesDashboardService::class)->summary($filters->asOf());
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
     *         marked_today: int
     *     }>,
     *     totals: array{
     *         students: int,
     *         present_today: int,
     *         absent_today: int,
     *         leave_today: int,
     *         marked_today: int
     *     }
     * }
     */
    public function batchOverview(?DashboardFilters $filters = null): array
    {
        $filters ??= DashboardFilters::default();

        return $this->remember($filters->cacheKey('batch_overview_attendance'), self::STATS_CACHE_SECONDS, function () use ($filters): array {
            $asOf = $filters->asOf();

            $batches = $this->batchQuery($filters)
                ->with('course')
                ->orderBy('name')
                ->get();

            $emptyTotals = [
                'students' => 0,
                'present_today' => 0,
                'absent_today' => 0,
                'leave_today' => 0,
                'marked_today' => 0,
            ];

            if ($batches->isEmpty()) {
                return [
                    'date_label' => $asOf->format('d M Y'),
                    'rows' => [],
                    'totals' => $emptyTotals,
                ];
            }

            $batchIds = $batches->pluck('id');

            $attendanceByBatch = Attendance::query()
                ->whereIn('batch_id', $batchIds)
                ->whereDate('attendance_date', $asOf)
                ->get(['batch_id', 'status'])
                ->groupBy('batch_id');

            $studentsByBatch = BatchStudent::query()
                ->whereIn('batch_id', $batchIds)
                ->where('is_active', true)
                ->get(['batch_id', 'student_id'])
                ->groupBy('batch_id');

            $rows = [];
            $totals = $emptyTotals;

            foreach ($batches as $batch) {
                $batchStudentIds = $studentsByBatch->get($batch->id, collect())->pluck('student_id');
                $students = $batchStudentIds->count();
                $markedRows = $attendanceByBatch->get($batch->id, collect());
                $present = $markedRows->where('status', AttendanceStatus::Present)->count();
                $absent = $markedRows->where('status', AttendanceStatus::Absent)->count();
                $leave = $markedRows->where('status', AttendanceStatus::Leave)->count();
                $marked = $markedRows->count();

                $rows[] = [
                    'id' => $batch->id,
                    'label' => $batch->selectLabel(),
                    'students' => $students,
                    'present_today' => $present,
                    'absent_today' => $absent,
                    'leave_today' => $leave,
                    'marked_today' => $marked,
                ];

                $totals['students'] += $students;
                $totals['present_today'] += $present;
                $totals['absent_today'] += $absent;
                $totals['leave_today'] += $leave;
                $totals['marked_today'] += $marked;
            }

            return [
                'date_label' => $asOf->format('d M Y'),
                'rows' => $rows,
                'totals' => $totals,
            ];
        });
    }

    /**
     * @return Builder<Batch>
     */
    protected function batchQuery(DashboardFilters $filters): Builder
    {
        return Batch::query()
            ->where('status', BatchStatus::Active)
            ->when($filters->sessionId, fn (Builder $query, int $sessionId) => $query->where(
                fn (Builder $scoped) => $scoped
                    ->where('academic_session_id', $sessionId)
                    ->orWhereNull('academic_session_id'),
            ))
            ->when($filters->courseId, fn (Builder $query, int $courseId) => $query->where('course_id', $courseId))
            ->when($filters->batchId, fn (Builder $query, int $batchId) => $query->whereKey($batchId));
    }

    /**
     * @return Builder<Enrollment>
     */
    protected function activeEnrollmentsQuery(DashboardFilters $filters): Builder
    {
        return Enrollment::query()
            ->where('is_active', true)
            ->where('status', EnrollmentStatus::Enrolled)
            ->when($filters->sessionId, fn (Builder $query, int $sessionId) => $query->where(
                fn (Builder $scoped) => $scoped
                    ->where('academic_session_id', $sessionId)
                    ->orWhereNull('academic_session_id'),
            ))
            ->when($filters->courseId, fn (Builder $query, int $courseId) => $query->where('course_id', $courseId));
    }

    protected function pendingFeesTotal(DashboardFilters $filters): float
    {
        $enrollmentIds = $this->activeEnrollmentsQuery($filters)->pluck('id');

        if ($enrollmentIds->isEmpty()) {
            return 0.0;
        }

        return round((float) FeeStructure::query()
            ->whereIn('enrollment_id', $enrollmentIds)
            ->sum('pending_amount'), 2);
    }

    /**
     * @return array{present: int, marked: int, students_in_batches: int}
     */
    protected function attendanceTotals(DashboardFilters $filters): array
    {
        $batchIds = $this->batchQuery($filters)->pluck('id');

        if ($batchIds->isEmpty()) {
            return ['present' => 0, 'marked' => 0, 'students_in_batches' => 0];
        }

        $studentsInBatches = (int) BatchStudent::query()
            ->whereIn('batch_id', $batchIds)
            ->where('is_active', true)
            ->count();

        $markedRows = Attendance::query()
            ->whereIn('batch_id', $batchIds)
            ->whereDate('attendance_date', $filters->asOf())
            ->get(['status']);

        return [
            'present' => $markedRows->where('status', AttendanceStatus::Present)->count(),
            'marked' => $markedRows->count(),
            'students_in_batches' => $studentsInBatches,
        ];
    }

    protected static function cacheVersion(): int
    {
        return (int) Cache::get(self::VERSION_CACHE_KEY, 1);
    }

    protected function remember(string $key, int $seconds, callable $callback): mixed
    {
        return Cache::remember($key.'.v'.self::cacheVersion(), $seconds, $callback);
    }

    public static function flushStatsCache(): void
    {
        self::flushAllCaches();
    }

    /**
     * Every dashboard read is namespaced by a version counter, so bumping the
     * counter retires all filtered variants at once.
     */
    public static function flushAllCaches(): void
    {
        Cache::forever(self::VERSION_CACHE_KEY, self::cacheVersion() + 1);
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function monthlyAdmissions(?DashboardFilters $filters = null): array
    {
        $filters ??= DashboardFilters::default();

        return $this->remember($filters->cacheKey('monthly_admissions'), self::CHART_CACHE_SECONDS, function () use ($filters): array {
            $labels = [];
            $data = [];

            foreach ($this->monthsForChart($filters) as $month) {
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
    public function monthlyFeeCollection(?DashboardFilters $filters = null): array
    {
        $filters ??= DashboardFilters::default();

        return $this->remember($filters->cacheKey('monthly_fees'), self::CHART_CACHE_SECONDS, function () use ($filters): array {
            $labels = [];
            $data = [];

            foreach ($this->monthsForChart($filters) as $month) {
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
     * Charts always show at least six months of context so a one-day filter
     * still produces a readable trend.
     *
     * @return list<Carbon>
     */
    protected function monthsForChart(DashboardFilters $filters): array
    {
        $months = max(6, $filters->monthsInRange());
        $end = $filters->to->copy()->startOfMonth();
        $series = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $series[] = $end->copy()->subMonths($i);
        }

        return $series;
    }

    /**
     * @return array{labels: array<int, string>, data: array<int, int>}
     */
    public function leadSourceBreakdown(?DashboardFilters $filters = null): array
    {
        $filters ??= DashboardFilters::default();

        return $this->remember($filters->cacheKey('lead_sources'), self::CHART_CACHE_SECONDS, function () use ($filters): array {
            $counts = Enquiry::query()
                ->whereDate('created_at', '>=', $filters->from->toDateString())
                ->whereDate('created_at', '<=', $filters->to->toDateString())
                ->when($filters->courseId, fn (Builder $query, int $courseId) => $query->where('course_id', $courseId))
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
    public function courseWiseAdmissions(?DashboardFilters $filters = null): array
    {
        $filters ??= DashboardFilters::default();

        return $this->remember($filters->cacheKey('course_admissions'), self::CHART_CACHE_SECONDS, function () use ($filters): array {
            $rows = Admission::query()
                ->where('admissions.status', AdmissionStatus::Approved)
                ->whereDate('admissions.approved_at', '>=', $filters->from->toDateString())
                ->whereDate('admissions.approved_at', '<=', $filters->to->toDateString())
                ->join('enquiries', 'admissions.enquiry_id', '=', 'enquiries.id')
                ->join('courses', 'enquiries.course_id', '=', 'courses.id')
                ->when($filters->courseId, fn ($query, int $courseId) => $query->where('courses.id', $courseId))
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
    public function recentEnquiries(int $limit = 5, ?DashboardFilters $filters = null): Collection
    {
        $filters ??= DashboardFilters::default();

        return $this->remember($filters->cacheKey("recent_enquiries.{$limit}"), self::STATS_CACHE_SECONDS, function () use ($limit, $filters): Collection {
            return Enquiry::query()
                ->with(['student', 'course'])
                ->when($filters->courseId, fn (Builder $query, int $courseId) => $query->where('course_id', $courseId))
                ->latest()
                ->limit($limit)
                ->get();
        });
    }

    /**
     * @return Collection<int, Admission>
     */
    public function pendingAdmissions(int $limit = 5, ?DashboardFilters $filters = null): Collection
    {
        $filters ??= DashboardFilters::default();

        return $this->remember($filters->cacheKey("pending_admissions.{$limit}"), self::STATS_CACHE_SECONDS, function () use ($limit): Collection {
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
