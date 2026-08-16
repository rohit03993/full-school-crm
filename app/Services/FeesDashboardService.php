<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FeeMiscChargeKind;
use App\Enums\FeeMiscChargeStatus;
use App\Enums\PaymentCancellationRequestStatus;
use App\Enums\PaymentMode;
use App\Models\FeeInstallment;
use App\Models\FeeMiscCharge;
use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\PaymentCancellationRequest;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FeesDashboardService
{
    public function __construct(
        protected FeeDiscountHistoryService $discounts,
    ) {}

    /**
     * @return array{
     *     collection_today: float,
     *     collection_month: float,
     *     pending_fees_total: float,
     *     pending_penalties_total: float,
     *     overdue_installment_count: int,
     *     overdue_students_count: int,
     *     overdue_amount: float,
     * }
     */
    public function summary(?Carbon $asOf = null): array
    {
        $today = ($asOf ?? now())->toDateString();
        $monthStart = ($asOf ?? now())->copy()->startOfMonth()->toDateString();

        $overdueInstallments = $this->overdueInstallmentsQuery($asOf)->get();
        $overdueStudents = $overdueInstallments
            ->map(fn (FeeInstallment $row): ?int => $row->feeStructure?->enrollment?->student_id)
            ->filter()
            ->unique()
            ->count();

        return [
            'collection_today' => round((float) Payment::query()->active()->whereDate('payment_date', $today)->sum('amount'), 2),
            'collection_month' => round((float) Payment::query()
                ->active()
                ->whereDate('payment_date', '>=', $monthStart)
                ->whereDate('payment_date', '<=', $today)
                ->sum('amount'), 2),
            'pending_fees_total' => round((float) FeeStructure::query()->forActiveEnrollments()->sum('pending_amount'), 2),
            'pending_penalties_total' => round((float) FeeMiscCharge::query()
                ->where('kind', FeeMiscChargeKind::LateFeePenalty)
                ->whereIn('status', [FeeMiscChargeStatus::Pending, FeeMiscChargeStatus::Partial])
                ->whereHas('feeStructure.enrollment', fn ($query) => $query
                    ->where('is_active', true)
                    ->where('status', EnrollmentStatus::Enrolled))
                ->get()
                ->sum(fn (FeeMiscCharge $charge): float => $charge->pendingAmount()), 2),
            'overdue_installment_count' => $overdueInstallments->count(),
            'overdue_students_count' => $overdueStudents,
            'overdue_amount' => round((float) $overdueInstallments->sum('pending_amount'), 2),
        ];
    }

    /**
     * Overview analytics for a selected collection period, plus live snapshots.
     *
     * @return array{
     *     collection_today: float,
     *     collection_month: float,
     *     collection_range: float,
     *     receipt_count: int,
     *     average_receipt: float,
     *     pending_fees_total: float,
     *     pending_penalties_total: float,
     *     overdue_installment_count: int,
     *     overdue_students_count: int,
     *     overdue_amount: float,
     *     cancelled_count: int,
     *     cancelled_total: float,
     *     range_label: string,
     *     payment_modes: list<array{mode: string, label: string, count: int, total: float, pct: float}>,
     *     discounts: array<string, int|float>,
     *     last_seven_days: list<array{date: string, day: string, is_today: bool, amount: float, height: float, display: string}>,
     *     monthly: array{labels: list<string>, data: list<float>, max: float},
     * }
     */
    public function overview(Carbon $from, Carbon $to): array
    {
        [$from, $to] = $this->normalizeRange($from, $to);
        $snapshot = $this->summary($to->copy());

        $collectionRange = round((float) Payment::query()
            ->active()
            ->whereDate('payment_date', '>=', $from->toDateString())
            ->whereDate('payment_date', '<=', $to->toDateString())
            ->sum('amount'), 2);
        $receiptCount = (int) Payment::query()
            ->active()
            ->whereDate('payment_date', '>=', $from->toDateString())
            ->whereDate('payment_date', '<=', $to->toDateString())
            ->count();
        $averageReceipt = $receiptCount > 0
            ? round($collectionRange / $receiptCount, 2)
            : 0.0;

        $cancelled = $this->cancelledInRange($from, $to);
        $discounts = $this->discounts->summaryInRange($from, $to);

        return array_merge($snapshot, [
            'collection_range' => $collectionRange,
            'receipt_count' => $receiptCount,
            'average_receipt' => $averageReceipt,
            'cancelled_count' => $cancelled['count'],
            'cancelled_total' => $cancelled['total'],
            'range_label' => $this->rangeLabel($from, $to),
            'payment_modes' => $this->paymentModesInRange($from, $to, $collectionRange),
            'discounts' => $discounts,
            'last_seven_days' => $this->lastSevenDaysSeries($to),
            'monthly' => $this->monthlyCollectionSeries($to),
        ]);
    }

    /**
     * @return Collection<int, array{
     *     student_id: int,
     *     student_name: string,
     *     enrollment_number: ?string,
     *     course_name: ?string,
     *     mobile: ?string,
     *     pending_amount: float,
     *     next_due_date: ?string,
     *     days_overdue: int,
     *     overdue_installments: int,
     *     profile_url: string,
     * }>
     */
    public function defaulters(?Carbon $asOf = null, ?int $limit = null): Collection
    {
        $today = ($asOf ?? now())->copy()->startOfDay();

        $installments = $this->overdueInstallmentsQuery($asOf)
            ->with([
                'feeStructure.enrollment.student',
                'feeStructure.enrollment.course',
            ])
            ->get();

        $rows = $installments
            ->groupBy(fn (FeeInstallment $row): int => (int) $row->feeStructure?->enrollment?->student_id)
            ->filter(fn (Collection $rows, int $studentId): bool => $studentId > 0)
            ->map(function (Collection $rows) use ($today): array {
                /** @var FeeInstallment $first */
                $first = $rows->sortBy(fn (FeeInstallment $row): string => $row->due_date?->toDateString() ?? '9999-12-31')->first();
                $enrollment = $first->feeStructure?->enrollment;
                $student = $enrollment?->student;
                $dueDate = $first->due_date;
                $daysOverdue = $dueDate ? max(0, (int) $dueDate->diffInDays($today)) : 0;

                return [
                    'student_id' => (int) $student?->id,
                    'student_name' => (string) ($student?->name ?? 'Student'),
                    'enrollment_number' => $enrollment?->enrollment_number,
                    'course_name' => $enrollment?->course?->name,
                    'mobile' => $student?->mobile,
                    'pending_amount' => round((float) $rows->sum('pending_amount'), 2),
                    'next_due_date' => $dueDate?->toDateString(),
                    'days_overdue' => $daysOverdue,
                    'overdue_installments' => $rows->count(),
                    'profile_url' => $student
                        ? \App\Filament\Pages\StudentProfilePage::getUrl([
                            'record' => $student->id,
                            'tab' => 'fees',
                            'collect' => 1,
                        ])
                        : '#',
                ];
            })
            ->sortByDesc('days_overdue')
            ->values();

        if ($limit !== null) {
            return $rows->take($limit)->values();
        }

        return $rows;
    }

    /**
     * @return array{
     *     rows: Collection<int, array<string, mixed>>,
     *     total: int,
     *     page: int,
     *     last_page: int,
     *     per_page: int,
     * }
     */
    public function paginateDefaulters(?Carbon $asOf = null, int $page = 1, int $perPage = 15): array
    {
        $perPage = max(1, $perPage);
        $all = $this->defaulters($asOf);
        $total = $all->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $lastPage);

        return [
            'rows' => $all->forPage($page, $perPage)->values(),
            'total' => $total,
            'page' => $page,
            'last_page' => $lastPage,
            'per_page' => $perPage,
        ];
    }

    /**
     * @return array{label: string, color: string}|null
     */
    public function feeStatusForStudent(Student $student): ?array
    {
        $feeStructure = $student->activeEnrollment?->feeStructure;

        if (! $feeStructure) {
            return null;
        }

        $pending = round((float) $feeStructure->pending_amount, 2);
        $penalties = round((float) $feeStructure->pendingPenaltiesTotal(), 2);

        if ($pending <= 0 && $penalties <= 0) {
            return ['label' => 'Paid', 'color' => 'success'];
        }

        $hasOverdue = $feeStructure->installments
            ->contains(fn (FeeInstallment $row): bool => $row->isOverdue());

        if ($hasOverdue) {
            return ['label' => 'Overdue', 'color' => 'danger'];
        }

        return ['label' => 'Pending', 'color' => 'warning'];
    }

    public function nextDueDateForStudent(Student $student): ?Carbon
    {
        $installments = $student->activeEnrollment?->feeStructure?->installments;

        if (! $installments || $installments->isEmpty()) {
            return null;
        }

        $next = $installments
            ->filter(fn (FeeInstallment $row): bool => (float) $row->pending_amount > 0 && $row->due_date !== null)
            ->sortBy(fn (FeeInstallment $row): string => $row->due_date->toDateString())
            ->first();

        return $next?->due_date;
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function normalizeRange(Carbon $from, Carbon $to): array
    {
        $from = $from->copy()->startOfDay();
        $to = $to->copy()->startOfDay();

        return $to->lt($from) ? [$to, $from] : [$from, $to];
    }

    public function rangeLabel(Carbon $from, Carbon $to): string
    {
        if ($from->isSameDay($to)) {
            return $to->format('d M Y');
        }

        return $from->format('d M').' – '.$to->format('d M Y');
    }

    /**
     * @return list<array{mode: string, label: string, count: int, total: float, pct: float}>
     */
    public function paymentModesInRange(Carbon $from, Carbon $to, ?float $rangeTotal = null): array
    {
        [$from, $to] = $this->normalizeRange($from, $to);

        $rows = Payment::query()
            ->active()
            ->selectRaw('payment_mode, COUNT(*) as receipt_count, COALESCE(SUM(amount), 0) as total')
            ->whereDate('payment_date', '>=', $from->toDateString())
            ->whereDate('payment_date', '<=', $to->toDateString())
            ->groupBy('payment_mode')
            ->orderByDesc('total')
            ->get();

        $total = $rangeTotal ?? round((float) $rows->sum('total'), 2);

        return $rows->map(function ($row) use ($total): array {
            $mode = $row->payment_mode instanceof PaymentMode
                ? $row->payment_mode
                : PaymentMode::tryFrom((string) $row->payment_mode);
            $value = $mode?->value ?? (string) $row->payment_mode;
            $amount = round((float) $row->total, 2);

            return [
                'mode' => $value,
                'label' => $mode?->label() ?? $value,
                'count' => (int) $row->receipt_count,
                'total' => $amount,
                'pct' => $total > 0 ? round(($amount / $total) * 100, 1) : 0.0,
            ];
        })->values()->all();
    }

    /**
     * @return list<array{date: string, day: string, is_today: bool, amount: float, height: float, display: string}>
     */
    public function lastSevenDaysSeries(?Carbon $asOf = null): array
    {
        $end = ($asOf ?? today())->copy()->startOfDay();
        $series = [];
        $amounts = [];

        for ($i = 6; $i >= 0; $i--) {
            $day = $end->copy()->subDays($i);
            $amount = round((float) Payment::query()
                ->active()
                ->whereDate('payment_date', $day->toDateString())
                ->sum('amount'), 2);
            $amounts[] = $amount;
            $series[] = [
                'date' => $day->toDateString(),
                'day' => $day->format('D'),
                'is_today' => $day->isSameDay(today()),
                'amount' => $amount,
            ];
        }

        $max = max(1.0, ...$amounts);

        return array_map(function (array $row) use ($max): array {
            $row['height'] = round(($row['amount'] / $max) * 100, 1);
            $row['display'] = $row['amount'] > 0
                ? ($row['amount'] >= 1000
                    ? '₹'.number_format($row['amount'] / 1000, $row['amount'] >= 10000 ? 0 : 1).'k'
                    : '₹'.number_format($row['amount'], 0))
                : '—';

            return $row;
        }, $series);
    }

    /**
     * @return array{labels: list<string>, data: list<float>, max: float}
     */
    public function monthlyCollectionSeries(?Carbon $asOf = null, int $months = 6): array
    {
        $end = ($asOf ?? today())->copy()->startOfMonth();
        $labels = [];
        $data = [];

        for ($i = $months - 1; $i >= 0; $i--) {
            $month = $end->copy()->subMonths($i);
            $labels[] = $month->format('M Y');
            $data[] = round((float) Payment::query()
                ->active()
                ->whereYear('payment_date', $month->year)
                ->whereMonth('payment_date', $month->month)
                ->sum('amount'), 2);
        }

        return [
            'labels' => $labels,
            'data' => $data,
            'max' => max(1.0, ...$data),
        ];
    }

    /**
     * @return array{count: int, total: float}
     */
    protected function cancelledInRange(Carbon $from, Carbon $to): array
    {
        if (! PaymentCancellationRequest::schemaReady()) {
            return ['count' => 0, 'total' => 0.0];
        }

        $row = PaymentCancellationRequest::query()
            ->where('payment_cancellation_requests.status', PaymentCancellationRequestStatus::Approved)
            ->whereBetween('payment_cancellation_requests.reviewed_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->leftJoin('payments', 'payments.id', '=', 'payment_cancellation_requests.payment_id')
            ->selectRaw('COUNT(payment_cancellation_requests.id) as entry_count, COALESCE(SUM(payments.amount), 0) as entry_total')
            ->first();

        return [
            'count' => (int) ($row->entry_count ?? 0),
            'total' => round((float) ($row->entry_total ?? 0), 2),
        ];
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<FeeInstallment>
     */
    protected function overdueInstallmentsQuery(?Carbon $asOf = null): \Illuminate\Database\Eloquent\Builder
    {
        $today = ($asOf ?? now())->toDateString();

        return FeeInstallment::query()
            ->where('pending_amount', '>', 0)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today)
            ->whereHas('feeStructure.enrollment', fn ($query) => $query
                ->where('is_active', true)
                ->where('status', EnrollmentStatus::Enrolled));
    }
}
