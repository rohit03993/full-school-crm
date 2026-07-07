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
        if ($charge->kind === FeeMiscChargeKind::Bundled) {
            throw ValidationException::withMessages([
                'charge' => 'Charges included in the original fee plan cannot be cancelled here.',
            ]);
        }

        if ($charge->status !== FeeMiscChargeStatus::Pending) {
            throw ValidationException::withMessages([
                'charge' => 'Only pending charges can be cancelled.',
            ]);
        }

        $charge->update([
            'status' => FeeMiscChargeStatus::Cancelled,
        ]);

        $this->audit->log(
            action: 'Misc Charge Cancelled',
            auditable: $charge,
            oldValues: ['status' => FeeMiscChargeStatus::Pending->value],
            newValues: ['status' => FeeMiscChargeStatus::Cancelled->value, 'reason' => $reason],
            user: $staff,
        );

        return $charge->fresh();
    }

    public function markPaid(FeeMiscCharge $charge): FeeMiscCharge
    {
        $charge->update([
            'status' => FeeMiscChargeStatus::Paid,
            'paid_at' => now(),
        ]);

        return $charge->fresh();
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
}
