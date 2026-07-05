<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\AdmissionInstallmentPlan;
use App\Models\AdmissionMiscFee;
use App\Models\User;
use App\Support\FeePlanCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdmissionFeePlanService
{
    public function __construct(
        protected AuditService $audit,
        protected FeeDiscountLedgerService $discountLedger,
    ) {}

    /**
     * @param  array{
     *     discount_amount?: float|int|string|null,
     *     use_installment_plan?: bool|null,
     *     misc_fees?: array<int, array{label?: string, amount?: float|int|string|null}>|null,
     *     installment_plan?: array<int, array{label?: string, amount?: float|int|string|null, due_date?: string|null}>|null,
     * }  $data
     */
    public function sync(Admission $admission, array $data, ?User $staff = null): Admission
    {
        if (! $admission->canAdjustFees()) {
            throw ValidationException::withMessages([
                'admission' => 'Fees can only be changed before enrollment is created.',
            ]);
        }

        $discount = max(0, (float) ($data['discount_amount'] ?? $admission->discount_amount ?? 0));
        $courseFee = (float) $admission->course_fee;
        $useInstallmentPlan = (bool) ($data['use_installment_plan'] ?? $admission->use_installment_plan);
        $miscFees = $this->normalizeMiscFees($data['misc_fees'] ?? []);
        $installmentPlan = $useInstallmentPlan
            ? $this->normalizeInstallmentPlan($data['installment_plan'] ?? [])
            : [];

        if ($discount > $courseFee) {
            throw ValidationException::withMessages([
                'discount_amount' => 'Discount cannot be greater than the course fee.',
            ]);
        }

        $miscTotal = round((float) collect($miscFees)->sum('amount'), 2);
        $netFee = $admission->calculatedNetFee($discount, $miscTotal);

        if ($useInstallmentPlan) {
            $this->assertInstallmentPlanValid($installmentPlan, $netFee);
        }

        return DB::transaction(function () use (
            $admission,
            $discount,
            $netFee,
            $useInstallmentPlan,
            $miscFees,
            $installmentPlan,
            $staff,
        ): Admission {
            $previousDiscount = round((float) $admission->discount_amount, 2);

            $admission->update([
                'discount_amount' => $discount,
                'discount_set_by_user_id' => $staff && $discount > 0 ? $staff->id : null,
                'net_fee' => $netFee,
                'use_installment_plan' => $useInstallmentPlan,
            ]);

            $this->syncMiscFees($admission, $miscFees);
            $this->syncInstallmentPlan($admission, $installmentPlan);

            $this->discountLedger->recordAdmissionChange(
                $admission,
                $previousDiscount,
                round($discount, 2),
                $staff,
                'Admission fee plan saved',
            );

            $this->audit->log(
                action: 'Admission Fees Updated',
                auditable: $admission,
                newValues: [
                    'admission_number' => $admission->admission_number,
                    'discount_amount' => $discount,
                    'misc_fees_total' => round((float) collect($miscFees)->sum('amount'), 2),
                    'net_fee' => $netFee,
                    'use_installment_plan' => $useInstallmentPlan,
                    'installment_count' => count($installmentPlan),
                ],
                user: $staff,
            );

            return $admission->fresh(['miscFees', 'installmentPlans', 'enquiry.course']);
        });
    }

    /**
     * @param  array<int, array{label?: string, amount?: mixed}>  $rows
     * @return array<int, array{label: string, amount: float, sort_order: int}>
     */
    public function normalizeMiscFees(array $rows): array
    {
        $normalized = [];

        foreach (array_values($rows) as $index => $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $amount = round((float) ($row['amount'] ?? 0), 2);

            if ($label === '' && $amount <= 0) {
                continue;
            }

            if ($label === '') {
                throw ValidationException::withMessages([
                    'misc_fees' => 'Each miscellaneous charge needs a label.',
                ]);
            }

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'misc_fees' => "Amount for “{$label}” must be greater than zero.",
                ]);
            }

            $normalized[] = [
                'label' => $label,
                'amount' => $amount,
                'sort_order' => $index + 1,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{label?: string, amount?: mixed, due_date?: string|null}>  $rows
     * @return array<int, array{label: string, amount: float, due_date: ?string, sort_order: int}>
     */
    public function normalizeInstallmentPlan(array $rows): array
    {
        $normalized = [];

        foreach (array_values($rows) as $index => $row) {
            $label = trim((string) ($row['label'] ?? ''));
            $amount = round((float) ($row['amount'] ?? 0), 2);
            $dueDate = filled($row['due_date'] ?? null) ? (string) $row['due_date'] : null;

            if ($label === '' && $amount <= 0) {
                continue;
            }

            if ($label === '') {
                throw ValidationException::withMessages([
                    'installment_plan' => 'Each installment needs a label.',
                ]);
            }

            if ($amount <= 0) {
                throw ValidationException::withMessages([
                    'installment_plan' => "Amount for “{$label}” must be greater than zero.",
                ]);
            }

            $normalized[] = [
                'label' => $label,
                'amount' => $amount,
                'due_date' => $dueDate,
                'sort_order' => $index + 1,
            ];
        }

        if ($normalized === []) {
            return [];
        }

        $sorted = FeePlanCalculator::sortInstallmentPlanByDueDate(array_map(
            fn (array $row): array => [
                'label' => $row['label'],
                'amount' => (string) $row['amount'],
                'due_date' => $row['due_date'],
            ],
            $normalized,
        ));

        return array_map(
            fn (array $row, int $index): array => [
                'label' => $row['label'],
                'amount' => round((float) $row['amount'], 2),
                'due_date' => $row['due_date'],
                'sort_order' => $index + 1,
            ],
            $sorted,
            array_keys($sorted),
        );
    }

    /**
     * @param  array<int, array{label: string, amount: float, due_date: ?string, sort_order: int}>  $plan
     */
    public function assertInstallmentPlanValid(array $plan, float $netFee): void
    {
        if ($plan === []) {
            throw ValidationException::withMessages([
                'installment_plan' => 'Add at least one installment row or turn off the installment plan.',
            ]);
        }

        $total = FeePlanCalculator::sumAmounts($plan);

        if (! FeePlanCalculator::isFullyAllocated($netFee, $plan)) {
            $remaining = FeePlanCalculator::remaining($netFee, $plan);
            $message = $remaining > 0
                ? 'Installment amounts are short by ₹'.number_format($remaining, 2).'. Add rows or fill balance on the last row.'
                : 'Installment amounts exceed the net fee by ₹'.number_format(abs($remaining), 2).'.';

            throw ValidationException::withMessages([
                'installment_plan' => $message,
            ]);
        }

        FeePlanCalculator::assertDueDatesInOrder($plan);
    }

    /**
     * @param  array<int, array{label: string, amount: float, sort_order: int}>  $miscFees
     */
    protected function syncMiscFees(Admission $admission, array $miscFees): void
    {
        $admission->miscFees()->delete();

        foreach ($miscFees as $row) {
            AdmissionMiscFee::query()->create([
                'admission_id' => $admission->id,
                ...$row,
            ]);
        }
    }

    /**
     * @param  array<int, array{label: string, amount: float, due_date: ?string, sort_order: int}>  $plan
     */
    protected function syncInstallmentPlan(Admission $admission, array $plan): void
    {
        $admission->installmentPlans()->delete();

        foreach ($plan as $row) {
            AdmissionInstallmentPlan::query()->create([
                'admission_id' => $admission->id,
                ...$row,
            ]);
        }
    }

    /**
     * @return array<int, array{label: string, amount: string, due_date: ?string}>
     */
    public function defaultInstallmentPlanRows(float $netFee): array
    {
        return FeePlanCalculator::defaultTwoPartPlan($netFee);
    }
}
