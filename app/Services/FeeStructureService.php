<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\Enrollment;
use App\Models\FeeMiscCharge;
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
        protected FeeInstallmentService $installments,
        protected FeeDiscountLedgerService $discountLedger,
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

        $admission->loadMissing(['miscFees', 'installmentPlans']);

        return DB::transaction(function () use ($enrollment, $admission, $staff): FeeStructure {
            $feeStructure = FeeStructure::query()->create([
                'enrollment_id' => $enrollment->id,
                'course_fee' => $admission->course_fee,
                'discount_amount' => $admission->discount_amount ?? 0,
                'discount_set_by_user_id' => $admission->discount_set_by_user_id,
                'net_fee' => $admission->net_fee,
                'paid_amount' => 0,
                'pending_amount' => $admission->net_fee,
                'set_by_user_id' => $staff->id,
            ]);

            foreach ($admission->miscFees as $index => $miscFee) {
                FeeMiscCharge::query()->create([
                    'fee_structure_id' => $feeStructure->id,
                    'label' => $miscFee->label,
                    'amount' => $miscFee->amount,
                    'sort_order' => $miscFee->sort_order ?: $index + 1,
                ]);
            }

            $this->audit->log(
                action: 'Fee Structure Created',
                auditable: $feeStructure,
                newValues: [
                    'enrollment_number' => $enrollment->enrollment_number,
                    'course_fee' => $feeStructure->course_fee,
                    'discount_amount' => $feeStructure->discount_amount,
                    'misc_fees_total' => $admission->miscFeesTotal(),
                    'net_fee' => $feeStructure->net_fee,
                    'use_installment_plan' => $admission->use_installment_plan,
                ],
                user: $staff,
            );

            $this->installments->createForFeeStructure($feeStructure, $enrollment, $admission);

            $this->discountLedger->linkAdmissionEntriesToFeeStructure($admission, $feeStructure);

            return $feeStructure->fresh(['installments', 'miscCharges', 'discountEntries']);
        });
    }

    /**
     * @param  array{
     *     course_fee: float|int|string,
     *     discount_amount: float|int|string,
     *     reason: string,
     *     reschedule_installments?: bool|null,
     *     installment_plan?: array<int, array<string, mixed>>|null,
     * }  $data
     */
    public function updateByAdmin(FeeStructure $feeStructure, array $data, User $admin): FeeStructure
    {
        Gate::forUser($admin)->authorize('update', $feeStructure);

        $courseFee = round((float) $data['course_fee'], 2);
        $discount = round(max(0, (float) $data['discount_amount']), 2);
        $reason = trim((string) ($data['reason'] ?? ''));
        $miscTotal = $feeStructure->miscChargesTotal();
        $reschedule = (bool) ($data['reschedule_installments'] ?? false);
        $installmentPlan = $reschedule
            ? app(AdmissionFeePlanService::class)->normalizeInstallmentPlan($data['installment_plan'] ?? [])
            : [];

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

        $newNet = round($courseFee - $discount + $miscTotal, 2);
        $paid = round((float) $feeStructure->paid_amount, 2);

        if ($newNet < $paid) {
            throw ValidationException::withMessages([
                'course_fee' => 'Net fee cannot be less than the amount already paid (₹'.number_format($paid, 2).').',
            ]);
        }

        $newPending = round($newNet - $paid, 2);

        if ($reschedule && $newPending > 0) {
            app(AdmissionFeePlanService::class)->assertInstallmentPlanValid($installmentPlan, $newPending);
        }

        return DB::transaction(function () use (
            $feeStructure,
            $courseFee,
            $discount,
            $newNet,
            $paid,
            $newPending,
            $reason,
            $admin,
            $reschedule,
            $installmentPlan,
        ): FeeStructure {
            $oldInstallmentSnapshot = $feeStructure->installments()
                ->orderBy('sort_order')
                ->get()
                ->map(fn ($row): array => [
                    'label' => $row->label,
                    'amount' => (float) $row->amount,
                    'paid' => (float) $row->paid_amount,
                    'pending' => (float) $row->pending_amount,
                    'due_date' => $row->due_date?->toDateString(),
                ])
                ->all();

            $oldValues = [
                'course_fee' => (float) $feeStructure->course_fee,
                'discount_amount' => (float) $feeStructure->discount_amount,
                'net_fee' => (float) $feeStructure->net_fee,
            ];

            $previousDiscount = $oldValues['discount_amount'];

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
                'discount_set_by_user_id' => $discount > 0 ? $admin->id : null,
                'net_fee' => $newNet,
                'pending_amount' => $newPending,
            ]);

            $this->discountLedger->recordFeeStructureChange(
                $feeStructure,
                $previousDiscount,
                $discount,
                $admin,
                $reason,
            );

            if ($reschedule && $newPending > 0) {
                $this->installments->reschedulePendingInstallments($feeStructure, $installmentPlan);
            } elseif ($reschedule) {
                $feeStructure->installments()->where('pending_amount', '>', 0)->delete();
            } else {
                $feeStructure->refresh();
                $this->installments->syncAfterFeeStructureChange($feeStructure);
            }

            $newInstallmentSnapshot = $feeStructure->fresh('installments')->installments
                ->map(fn ($row): array => [
                    'label' => $row->label,
                    'amount' => (float) $row->amount,
                    'paid' => (float) $row->paid_amount,
                    'pending' => (float) $row->pending_amount,
                    'due_date' => $row->due_date?->toDateString(),
                ])
                ->all();

            $this->audit->log(
                action: 'fee_structure_changed',
                auditable: $feeStructure,
                oldValues: [
                    ...$oldValues,
                    'installments' => $oldInstallmentSnapshot,
                ],
                newValues: [
                    'course_fee' => $courseFee,
                    'discount_amount' => $discount,
                    'net_fee' => $newNet,
                    'pending_amount' => $newPending,
                    'installments' => $newInstallmentSnapshot,
                    'rescheduled_installments' => $reschedule,
                ],
                reason: $reason,
                user: $admin,
            );

            return $feeStructure->fresh(['installments', 'miscCharges', 'discountEntries']);
        });
    }
}
