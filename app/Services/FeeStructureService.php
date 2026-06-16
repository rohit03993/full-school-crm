<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\FeeStructureHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class FeeStructureService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    public function createFromAdmission(Enrollment $enrollment, Admission $admission, User $staff): FeeStructure
    {
        if ($enrollment->feeStructure()->exists()) {
            throw ValidationException::withMessages([
                'enrollment' => 'Fee structure already exists for this enrollment.',
            ]);
        }

        if ($admission->net_fee === null || $admission->course_fee === null) {
            throw ValidationException::withMessages([
                'admission' => 'Admission is missing fee details. Re-convert with a valid course and fee.',
            ]);
        }

        $feeStructure = FeeStructure::query()->create([
            'enrollment_id' => $enrollment->id,
            'course_fee' => $admission->course_fee,
            'discount_amount' => $admission->discount_amount ?? 0,
            'net_fee' => $admission->net_fee,
            'paid_amount' => 0,
            'pending_amount' => $admission->net_fee,
            'set_by_user_id' => $staff->id,
        ]);

        $this->audit->log(
            action: 'Fee Structure Created',
            auditable: $feeStructure,
            newValues: [
                'enrollment_number' => $enrollment->enrollment_number,
                'course_fee' => $feeStructure->course_fee,
                'discount_amount' => $feeStructure->discount_amount,
                'net_fee' => $feeStructure->net_fee,
            ],
            user: $staff,
        );

        return $feeStructure;
    }

    /**
     * @param  array{
     *     course_fee: float|int|string,
     *     discount_amount: float|int|string,
     *     reason: string,
     * }  $data
     */
    public function updateByAdmin(FeeStructure $feeStructure, array $data, User $admin): FeeStructure
    {
        Gate::forUser($admin)->authorize('update', $feeStructure);

        $courseFee = round((float) $data['course_fee'], 2);
        $discount = round(max(0, (float) $data['discount_amount']), 2);
        $reason = trim((string) ($data['reason'] ?? ''));

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required when changing fees after enrollment.',
            ]);
        }

        if ($discount > $courseFee) {
            throw ValidationException::withMessages([
                'discount_amount' => 'Discount cannot exceed course fee.',
            ]);
        }

        $newNet = round($courseFee - $discount, 2);
        $paid = round((float) $feeStructure->paid_amount, 2);

        if ($newNet < $paid) {
            throw ValidationException::withMessages([
                'course_fee' => 'Net fee cannot be less than the amount already paid (₹'.number_format($paid, 2).').',
            ]);
        }

        return DB::transaction(function () use ($feeStructure, $courseFee, $discount, $newNet, $paid, $reason, $admin): FeeStructure {
            $oldValues = [
                'course_fee' => (float) $feeStructure->course_fee,
                'discount_amount' => (float) $feeStructure->discount_amount,
                'net_fee' => (float) $feeStructure->net_fee,
            ];

            FeeStructureHistory::query()->create([
                'fee_structure_id' => $feeStructure->id,
                'old_course_fee' => $oldValues['course_fee'],
                'new_course_fee' => $courseFee,
                'old_discount' => $oldValues['discount_amount'],
                'new_discount' => $discount,
                'old_net_fee' => $oldValues['net_fee'],
                'new_net_fee' => $newNet,
                'changed_by_user_id' => $admin->id,
                'reason' => $reason,
                'changed_at' => now(),
            ]);

            $feeStructure->update([
                'course_fee' => $courseFee,
                'discount_amount' => $discount,
                'net_fee' => $newNet,
                'pending_amount' => round($newNet - $paid, 2),
            ]);

            $this->audit->log(
                action: 'fee_structure_changed',
                auditable: $feeStructure,
                oldValues: $oldValues,
                newValues: [
                    'course_fee' => $courseFee,
                    'discount_amount' => $discount,
                    'net_fee' => $newNet,
                    'pending_amount' => round($newNet - $paid, 2),
                ],
                reason: $reason,
                user: $admin,
            );

            return $feeStructure->fresh();
        });
    }
}
