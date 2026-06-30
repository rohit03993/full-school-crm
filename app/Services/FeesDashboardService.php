<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\FeePenaltyStatus;
use App\Models\FeeInstallment;
use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\Student;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class FeesDashboardService
{
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
            'collection_today' => round((float) Payment::query()->whereDate('payment_date', $today)->sum('amount'), 2),
            'collection_month' => round((float) Payment::query()
                ->whereDate('payment_date', '>=', $monthStart)
                ->whereDate('payment_date', '<=', $today)
                ->sum('amount'), 2),
            'pending_fees_total' => round((float) FeeStructure::query()->forActiveEnrollments()->sum('pending_amount'), 2),
            'pending_penalties_total' => round((float) \App\Models\FeePenalty::query()
                ->where('status', FeePenaltyStatus::Pending)
                ->whereHas('feeStructure.enrollment', fn ($query) => $query
                    ->where('is_active', true)
                    ->where('status', EnrollmentStatus::Enrolled))
                ->sum('penalty_amount'), 2),
            'overdue_installment_count' => $overdueInstallments->count(),
            'overdue_students_count' => $overdueStudents,
            'overdue_amount' => round((float) $overdueInstallments->sum('pending_amount'), 2),
        ];
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
    public function defaulters(?Carbon $asOf = null, int $limit = 100): Collection
    {
        $today = ($asOf ?? now())->copy()->startOfDay();

        $installments = $this->overdueInstallmentsQuery($asOf)
            ->with([
                'feeStructure.enrollment.student',
                'feeStructure.enrollment.course',
            ])
            ->get();

        return $installments
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
                        ? \App\Filament\Pages\StudentProfilePage::getUrl(['record' => $student->id, 'tab' => 'fees'])
                        : '#',
                ];
            })
            ->sortByDesc('days_overdue')
            ->values()
            ->take($limit);
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
