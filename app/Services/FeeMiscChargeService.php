<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Enums\FeeMiscChargeKind;
use App\Enums\FeeMiscChargeStatus;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FeeMiscCharge;
use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeeMiscChargeService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    public function addSeparateCharge(
        FeeStructure $feeStructure,
        string $label,
        float $amount,
        ?string $dueDate,
        User $staff,
    ): FeeMiscCharge {
        $label = trim($label);
        $amount = round($amount, 2);

        if ($label === '') {
            throw ValidationException::withMessages([
                'label' => 'Charge label is required.',
            ]);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Amount must be greater than zero.',
            ]);
        }

        $sortOrder = (int) $feeStructure->miscCharges()->max('sort_order') + 1;

        $charge = FeeMiscCharge::query()->create([
            'fee_structure_id' => $feeStructure->id,
            'label' => $label,
            'amount' => $amount,
            'paid_amount' => 0,
            'kind' => FeeMiscChargeKind::Separate,
            'status' => FeeMiscChargeStatus::Pending,
            'due_date' => $dueDate,
            'added_by_user_id' => $staff->id,
            'sort_order' => $sortOrder,
        ]);

        $this->audit->log(
            action: 'Misc Charge Added',
            auditable: $charge,
            newValues: [
                'student_id' => $feeStructure->enrollment?->student_id,
                'label' => $label,
                'amount' => $amount,
                'due_date' => $dueDate,
            ],
            user: $staff,
        );

        return $charge;
    }

    /**
     * @return list<FeeMiscCharge>
     */
    public function bulkAddForBatch(
        Batch $batch,
        string $label,
        float $amount,
        ?string $dueDate,
        User $staff,
    ): array {
        if ($batch->status !== BatchStatus::Active) {
            throw ValidationException::withMessages([
                'batch_id' => 'Cannot add charges for a non-active batch.',
            ]);
        }

        $batch->loadMissing('course');

        $enrollments = Enrollment::query()
            ->where('is_active', true)
            ->where('course_id', $batch->course_id)
            ->whereHas('student.activeBatchStudent', fn ($query) => $query
                ->where('batch_id', $batch->id)
                ->where('is_active', true))
            ->with('feeStructure')
            ->get();

        if ($enrollments->isEmpty()) {
            throw ValidationException::withMessages([
                'batch_id' => 'No active enrolled students found in this batch.',
            ]);
        }

        return DB::transaction(function () use ($enrollments, $label, $amount, $dueDate, $staff, $batch): array {
            $created = [];

            foreach ($enrollments as $enrollment) {
                $feeStructure = $enrollment->feeStructure;

                if (! $feeStructure) {
                    continue;
                }

                $created[] = $this->addSeparateCharge($feeStructure, $label, $amount, $dueDate, $staff);
            }

            if ($created === []) {
                throw ValidationException::withMessages([
                    'batch_id' => 'Students in this batch do not have fee records yet.',
                ]);
            }

            $this->audit->log(
                action: 'Bulk Misc Charges Added',
                auditable: $batch,
                newValues: [
                    'batch_id' => $batch->id,
                    'batch_name' => $batch->name,
                    'label' => $label,
                    'amount' => $amount,
                    'student_count' => count($created),
                ],
                user: $staff,
            );

            return $created;
        });
    }

    /**
     * @return list<FeeMiscCharge>
     */
    public function bulkAddForCourse(
        Course $course,
        string $label,
        float $amount,
        ?string $dueDate,
        User $staff,
        ?int $academicSessionId = null,
    ): array {
        $query = Enrollment::query()
            ->where('is_active', true)
            ->where('course_id', $course->id)
            ->with('feeStructure');

        if ($academicSessionId) {
            $query->where('academic_session_id', $academicSessionId);
        }

        $enrollments = $query->get();

        if ($enrollments->isEmpty()) {
            throw ValidationException::withMessages([
                'course_id' => 'No active enrollments found for this course.',
            ]);
        }

        return DB::transaction(function () use ($enrollments, $label, $amount, $dueDate, $staff, $course): array {
            $created = [];

            foreach ($enrollments as $enrollment) {
                $feeStructure = $enrollment->feeStructure;

                if (! $feeStructure) {
                    continue;
                }

                $created[] = $this->addSeparateCharge($feeStructure, $label, $amount, $dueDate, $staff);
            }

            if ($created === []) {
                throw ValidationException::withMessages([
                    'course_id' => 'Enrolled students do not have fee records yet.',
                ]);
            }

            $this->audit->log(
                action: 'Bulk Misc Charges Added',
                auditable: $course,
                newValues: [
                    'course_id' => $course->id,
                    'course_name' => $course->name,
                    'label' => $label,
                    'amount' => $amount,
                    'student_count' => count($created),
                ],
                user: $staff,
            );

            return $created;
        });
    }

    public function cancelCharge(FeeMiscCharge $charge, User $staff, ?string $reason = null): FeeMiscCharge
    {
        throw ValidationException::withMessages([
            'charge' => 'Misc charges cannot be cancelled. Use Adjust Fees to correct the fee plan if a charge was added in error.',
        ]);
    }

    public function waiveLateFeePenalty(FeeMiscCharge $charge, User $staff, string $reason): FeeMiscCharge
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required to waive a late fee penalty.',
            ]);
        }

        if (! $charge->isLateFeePenalty()) {
            throw ValidationException::withMessages([
                'charge' => 'Only late fee penalty charges can be waived here.',
            ]);
        }

        if ((float) $charge->paid_amount > 0) {
            throw ValidationException::withMessages([
                'charge' => 'Cannot waive a late fee that already has payments recorded.',
            ]);
        }

        if (! in_array($charge->status, [FeeMiscChargeStatus::Pending, FeeMiscChargeStatus::Partial], true)) {
            throw ValidationException::withMessages([
                'charge' => 'Only unpaid late fee penalties can be waived.',
            ]);
        }

        $charge->update([
            'status' => FeeMiscChargeStatus::Cancelled,
        ]);

        $this->audit->log(
            action: 'Late Fee Penalty Waived',
            auditable: $charge,
            newValues: ['status' => FeeMiscChargeStatus::Cancelled->value, 'reason' => $reason],
            user: $staff,
        );

        return $charge->fresh();
    }

    public function applyPayment(FeeMiscCharge $charge, float $amount): FeeMiscCharge
    {
        $amount = round($amount, 2);
        $total = round((float) $charge->amount, 2);
        $newPaid = round((float) $charge->paid_amount + $amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        if ($newPaid > $total + 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Payment exceeds the remaining balance of ₹'.number_format($charge->pendingAmount(), 2).'.',
            ]);
        }

        $status = $newPaid >= $total - 0.01
            ? FeeMiscChargeStatus::Paid
            : FeeMiscChargeStatus::Partial;

        $charge->update([
            'paid_amount' => $newPaid,
            'status' => $status,
            'paid_at' => $status === FeeMiscChargeStatus::Paid ? now() : null,
        ]);

        return $charge->fresh();
    }

    public function markPaid(FeeMiscCharge $charge): FeeMiscCharge
    {
        return $this->applyPayment($charge, $charge->pendingAmount());
    }

    public function resolveForStudent(Student $student, int $chargeId): FeeMiscCharge
    {
        $charge = FeeMiscCharge::query()
            ->whereKey($chargeId)
            ->whereHas('feeStructure.enrollment', fn ($query) => $query
                ->where('student_id', $student->id)
                ->where('is_active', true))
            ->first();

        if (! $charge) {
            throw ValidationException::withMessages([
                'fee_misc_charge_id' => 'Misc charge not found for this student.',
            ]);
        }

        return $charge;
    }

    /**
     * @return Collection<int, array{
     *     label: string,
     *     amount: float,
     *     due_date: ?string,
     *     student_count: int,
     *     pending_total: float,
     *     paid_total: float,
     *     added_at: \Illuminate\Support\Carbon|null,
     *     added_by: ?string,
     * }>
     */
    public function recentSeparateChargeSummaries(int $limit = 30): Collection
    {
        return $this->summarizeCharges(
            FeeMiscCharge::query()
                ->separatePayable()
                ->with('addedBy')
                ->orderByDesc('created_at')
                ->limit(500)
                ->get(),
            fn (FeeMiscCharge $charge): string => implode('|', [
                $charge->label,
                number_format((float) $charge->amount, 2, '.', ''),
                $charge->due_date?->toDateString() ?? '',
                $charge->created_at?->format('Y-m-d H:i') ?? '',
                (string) $charge->added_by_user_id,
            ]),
        )->take($limit)->values();
    }

    /**
     * @return Collection<int, array{
     *     label: string,
     *     amount: float,
     *     due_date: ?string,
     *     student_count: int,
     *     pending_total: float,
     *     paid_total: float,
     *     added_at: \Illuminate\Support\Carbon|null,
     *     added_by: ?string,
     * }>
     */
    public function scopeSeparateChargeSummariesForBatch(Batch $batch): Collection
    {
        $feeStructureIds = $this->activeFeeStructureIdsForBatch($batch);

        if ($feeStructureIds === []) {
            return collect();
        }

        return $this->summarizeCharges(
            FeeMiscCharge::query()
                ->separatePayable()
                ->whereIn('fee_structure_id', $feeStructureIds)
                ->with('addedBy')
                ->orderByDesc('created_at')
                ->get(),
            fn (FeeMiscCharge $charge): string => implode('|', [
                $charge->label,
                number_format((float) $charge->amount, 2, '.', ''),
                $charge->due_date?->toDateString() ?? '',
            ]),
        )->values();
    }

    /**
     * @return Collection<int, array{
     *     label: string,
     *     amount: float,
     *     due_date: ?string,
     *     student_count: int,
     *     pending_total: float,
     *     paid_total: float,
     *     added_at: \Illuminate\Support\Carbon|null,
     *     added_by: ?string,
     * }>
     */
    public function scopeSeparateChargeSummariesForCourse(Course $course, ?int $academicSessionId = null): Collection
    {
        $feeStructureIds = $this->activeFeeStructureIdsForCourse($course, $academicSessionId);

        if ($feeStructureIds === []) {
            return collect();
        }

        return $this->summarizeCharges(
            FeeMiscCharge::query()
                ->separatePayable()
                ->whereIn('fee_structure_id', $feeStructureIds)
                ->with('addedBy')
                ->orderByDesc('created_at')
                ->get(),
            fn (FeeMiscCharge $charge): string => implode('|', [
                $charge->label,
                number_format((float) $charge->amount, 2, '.', ''),
                $charge->due_date?->toDateString() ?? '',
            ]),
        )->values();
    }

    /**
     * @return list<int>
     */
    protected function activeFeeStructureIdsForBatch(Batch $batch): array
    {
        return Enrollment::query()
            ->where('is_active', true)
            ->where('course_id', $batch->course_id)
            ->whereHas('student.activeBatchStudent', fn ($query) => $query
                ->where('batch_id', $batch->id)
                ->where('is_active', true))
            ->whereHas('feeStructure')
            ->with('feeStructure:id,enrollment_id')
            ->get()
            ->map(fn (Enrollment $enrollment): int => (int) $enrollment->feeStructure->id)
            ->all();
    }

    /**
     * @return list<int>
     */
    protected function activeFeeStructureIdsForCourse(Course $course, ?int $academicSessionId = null): array
    {
        $query = Enrollment::query()
            ->where('is_active', true)
            ->where('course_id', $course->id)
            ->whereHas('feeStructure');

        if ($academicSessionId) {
            $query->where('academic_session_id', $academicSessionId);
        }

        return $query
            ->with('feeStructure:id,enrollment_id')
            ->get()
            ->map(fn (Enrollment $enrollment): int => (int) $enrollment->feeStructure->id)
            ->all();
    }

    /**
     * @param  Collection<int, FeeMiscCharge>  $charges
     * @return Collection<int, array{
     *     label: string,
     *     amount: float,
     *     due_date: ?string,
     *     student_count: int,
     *     pending_total: float,
     *     paid_total: float,
     *     added_at: \Illuminate\Support\Carbon|null,
     *     added_by: ?string,
     * }>
     */
    protected function summarizeCharges(Collection $charges, callable $groupKey): Collection
    {
        return $charges
            ->groupBy($groupKey)
            ->map(function (Collection $group): array {
                /** @var FeeMiscCharge $first */
                $first = $group->first();

                return [
                    'label' => (string) $first->label,
                    'amount' => (float) $first->amount,
                    'due_date' => $first->due_date?->toDateString(),
                    'student_count' => $group->count(),
                    'pending_total' => round((float) $group->sum(fn (FeeMiscCharge $charge): float => $charge->pendingAmount()), 2),
                    'paid_total' => round((float) $group->sum(fn (FeeMiscCharge $charge): float => (float) $charge->paid_amount), 2),
                    'added_at' => $first->created_at,
                    'added_by' => $first->addedBy?->name,
                ];
            })
            ->sortByDesc(fn (array $row) => $row['added_at']?->timestamp ?? 0)
            ->values();
    }
}
