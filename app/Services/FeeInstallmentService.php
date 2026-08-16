<?php

namespace App\Services;

use App\Enums\PaymentShortfallAction;
use App\Models\Admission;
use App\Models\Enrollment;
use App\Models\FeeInstallment;
use App\Models\FeeStructure;
use App\Models\Payment;
use App\Support\FeePlanCalculator;
use App\Support\PaymentShortfallHelper;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class FeeInstallmentService
{
    /**
     * @return Collection<int, FeeInstallment>
     */
    public function createForFeeStructure(FeeStructure $feeStructure, Enrollment $enrollment, Admission $admission): Collection
    {
        $feeStructure->installments()->delete();

        $netFee = round((float) $feeStructure->net_fee, 2);
        $admission->loadMissing('installmentPlans');

        if ($admission->use_installment_plan && $admission->installmentPlans->isNotEmpty()) {
            return $admission->installmentPlans
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($plan) => $this->createInstallment(
                    $feeStructure,
                    $plan->label,
                    (float) $plan->amount,
                    $plan->due_date?->toDateString(),
                    (int) $plan->sort_order,
                ));
        }

        return collect([
            $this->createInstallment($feeStructure, FeePlanCalculator::installmentLabel(1), $netFee, null, 1),
        ]);
    }

    /**
     * Replace all unpaid installments with a new schedule (paid rows are kept).
     *
     * @param  array<int, array{label: string, amount: float, due_date: ?string, sort_order: int}>  $plan
     */
    public function reschedulePendingInstallments(FeeStructure $feeStructure, array $plan): void
    {
        $target = round((float) $feeStructure->pending_amount, 2);

        if ($target <= 0 && $plan === []) {
            return;
        }

        if (! FeePlanCalculator::isFullyAllocated($target, $plan)) {
            $remaining = FeePlanCalculator::remaining($target, $plan);
            $message = $remaining > 0
                ? 'Installment amounts are short of the pending balance by ₹'.number_format($remaining, 2).'.'
                : 'Installment amounts exceed the pending balance by ₹'.number_format(abs($remaining), 2).'.';

            throw ValidationException::withMessages([
                'installment_plan' => $message,
            ]);
        }

        FeePlanCalculator::assertDueDatesInOrder($plan);

        $paidInstallments = $feeStructure->installments()
            ->where('pending_amount', '<=', 0)
            ->orderBy('sort_order')
            ->get();

        $feeStructure->installments()
            ->where('pending_amount', '>', 0)
            ->delete();

        $sortOrder = (int) $paidInstallments->max('sort_order');

        foreach ($plan as $row) {
            $sortOrder++;
            $this->createInstallment(
                $feeStructure,
                $row['label'],
                (float) $row['amount'],
                $row['due_date'],
                $sortOrder,
            );
        }

        $this->syncInstallmentSortOrder($feeStructure);
    }

    public function syncInstallmentSortOrder(FeeStructure $feeStructure): void
    {
        $installments = $feeStructure->installments()->get();

        $sorted = $installments->sortBy(fn (FeeInstallment $installment): array => [
            $installment->due_date?->toDateString() ?? '0000-01-01',
            $installment->sort_order,
            $installment->id,
        ])->values();

        foreach ($sorted as $index => $installment) {
            $attributes = ['sort_order' => $index + 1];

            if (FeePlanCalculator::isGeneratedInstallmentLabel($installment->label)) {
                $attributes['label'] = FeePlanCalculator::installmentLabel($index + 1);
            }

            $installment->updateQuietly($attributes);
        }
    }

    /** @deprecated Use syncInstallmentSortOrder() */
    public function syncInstallmentOrderAndLabels(FeeStructure $feeStructure): void
    {
        $this->syncInstallmentSortOrder($feeStructure);
    }

    public function applyPayment(FeeInstallment $installment, float $amount): void
    {
        $pending = round((float) $installment->pending_amount, 2);
        $amount = round($amount, 2);

        if ($amount > $pending) {
            throw ValidationException::withMessages([
                'amount' => "Amount exceeds pending for {$installment->label} (₹".number_format($pending, 2).').',
            ]);
        }

        $installment->update([
            'paid_amount' => round((float) $installment->paid_amount + $amount, 2),
            'pending_amount' => round($pending - $amount, 2),
        ]);
    }

    /**
     * FeesCRM-style allocation with staff-chosen shortfall handling on partial pay.
     *
     * @param  array{action?: string, due_date?: string|null, label?: string|null}|null  $shortfallHandling
     * @return array{
     *     shortfall_allocation: ?array<string, mixed>,
     *     surplus_allocation: ?array<string, mixed>,
     *     applications: list<array{installment_id: int, label: string, amount_applied: float, amount_before: float, paid_before: float, pending_before: float}>
     * }
     */
    public function allocatePayment(
        FeeStructure $feeStructure,
        ?FeeInstallment $startInstallment,
        float $amount,
        ?array $shortfallHandling = null,
    ): array {
        $amount = round($amount, 2);
        $remaining = $amount;
        $shortfallAllocation = null;
        $surplusAllocation = null;
        $surplusTargets = [];
        $applications = [];

        if ($remaining <= 0) {
            return [
                'shortfall_allocation' => null,
                'surplus_allocation' => null,
                'applications' => [],
            ];
        }

        $installments = $feeStructure->installments()
            ->where('pending_amount', '>', 0)
            ->orderBy('sort_order')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        if ($installments->isEmpty()) {
            return [
                'shortfall_allocation' => null,
                'surplus_allocation' => null,
                'applications' => [],
            ];
        }

        if ($startInstallment) {
            $startIndex = $installments->search(fn (FeeInstallment $row): bool => $row->id === $startInstallment->id);

            if ($startIndex !== false) {
                $installments = $installments->slice($startIndex)->values();
            }
        }

        $sourceInstallment = $installments->first();
        $isFirst = true;

        foreach ($installments as $installment) {
            if ($remaining <= 0.01) {
                break;
            }

            $amountBefore = round((float) $installment->amount, 2);
            $paidBefore = round((float) $installment->paid_amount, 2);
            $pendingBefore = round((float) $installment->pending_amount, 2);

            [$remaining, $allocation, $amountApplied] = $this->processFlexibleInstallmentPayment(
                $installment,
                $remaining,
                $isFirst ? $shortfallHandling : null,
            );

            if ($amountApplied > 0.01) {
                $applications[] = [
                    'installment_id' => $installment->id,
                    'label' => $installment->label,
                    'amount_applied' => $amountApplied,
                    'amount_before' => $amountBefore,
                    'paid_before' => $paidBefore,
                    'pending_before' => $pendingBefore,
                ];
            }

            if (! $isFirst && $amountApplied > 0.01) {
                $surplusTargets[] = [
                    'installment_id' => $installment->id,
                    'label' => $installment->label,
                    'amount' => $amountApplied,
                ];
            }

            if ($allocation !== null) {
                $shortfallAllocation = $allocation;
            }

            $isFirst = false;
        }

        if ($remaining > 0.01) {
            throw ValidationException::withMessages([
                'amount' => 'Payment could not be fully allocated to installments.',
            ]);
        }

        if ($surplusTargets !== [] && $sourceInstallment) {
            $surplusTotal = round(collect($surplusTargets)->sum('amount'), 2);

            $surplusAllocation = [
                'action' => PaymentShortfallAction::SurplusForward->value,
                'amount' => $surplusTotal,
                'source_installment_id' => $sourceInstallment->id,
                'source_label' => $sourceInstallment->label,
                'targets' => $surplusTargets,
            ];
        }

        return [
            'shortfall_allocation' => $shortfallAllocation,
            'surplus_allocation' => $surplusAllocation,
            'applications' => $applications,
        ];
    }

    /**
     * Undo installment mutations from the latest cancelled payment.
     */
    public function reversePaymentAllocation(Payment $payment): void
    {
        $snapshot = $payment->allocation_snapshot;

        if (! is_array($snapshot) || $snapshot === []) {
            $snapshot = $this->legacyAllocationSnapshotFromShortfall($payment);
        }

        $applications = $snapshot['applications'] ?? [];
        $shortfall = $snapshot['shortfall'] ?? null;

        if (! is_array($shortfall) && is_array($payment->shortfall_allocation)) {
            $action = $payment->shortfall_allocation['action'] ?? null;

            if (in_array($action, [
                PaymentShortfallAction::NewInstallment->value,
                PaymentShortfallAction::CarryForward->value,
            ], true)) {
                $shortfall = $payment->shortfall_allocation;
            }
        }

        if (is_array($shortfall)) {
            $this->reverseShortfallAllocation($shortfall);
        }

        foreach (array_reverse($applications) as $application) {
            if (! is_array($application)) {
                continue;
            }

            $installmentId = (int) ($application['installment_id'] ?? 0);
            $amountApplied = round((float) ($application['amount_applied'] ?? 0), 2);

            if ($installmentId <= 0 || $amountApplied <= 0.01) {
                continue;
            }

            $installment = FeeInstallment::query()->find($installmentId);

            if (! $installment) {
                continue;
            }

            $newPaid = round(max(0, (float) $installment->paid_amount - $amountApplied), 2);
            $newPending = round((float) $installment->pending_amount + $amountApplied, 2);
            $newAmount = round(max((float) $installment->amount, $newPaid + $newPending), 2);

            if (array_key_exists('amount_before', $application)) {
                $newAmount = round((float) $application['amount_before'], 2);
            }

            $installment->update([
                'paid_amount' => $newPaid,
                'pending_amount' => $newPending,
                'amount' => $newAmount,
            ]);
        }

        if ($payment->fee_structure_id) {
            $this->syncInstallmentSortOrder($payment->feeStructure()->first() ?? FeeStructure::query()->findOrFail($payment->fee_structure_id));
        }
    }

    /**
     * @param  array<string, mixed>  $shortfall
     */
    protected function reverseShortfallAllocation(array $shortfall): void
    {
        $action = PaymentShortfallAction::tryFrom((string) ($shortfall['action'] ?? ''));
        $amount = round((float) ($shortfall['amount'] ?? 0), 2);
        $sourceId = (int) ($shortfall['source_installment_id'] ?? 0);
        $targetId = (int) ($shortfall['target_installment_id'] ?? 0);

        if ($amount <= 0.01 || $sourceId <= 0) {
            return;
        }

        $source = FeeInstallment::query()->find($sourceId);

        if ($action === PaymentShortfallAction::NewInstallment && $targetId > 0) {
            $target = FeeInstallment::query()->find($targetId);

            if ($target) {
                if ((float) $target->paid_amount > 0.01) {
                    throw ValidationException::withMessages([
                        'payment' => 'Cannot cancel: the balance installment created by this payment already has collections. Cancel those payments first.',
                    ]);
                }

                $target->delete();
            }

            if ($source) {
                $source->refresh();
                $source->update([
                    'amount' => round((float) $source->amount + $amount, 2),
                    'pending_amount' => round((float) $source->pending_amount + $amount, 2),
                ]);
            }

            return;
        }

        if ($action === PaymentShortfallAction::CarryForward && $targetId > 0) {
            $target = FeeInstallment::query()->find($targetId);

            if ($target) {
                $target->update([
                    'amount' => round(max(0, (float) $target->amount - $amount), 2),
                    'pending_amount' => round(max(0, (float) $target->pending_amount - $amount), 2),
                ]);
            }

            if ($source) {
                $source->refresh();
                $source->update([
                    'amount' => round((float) $source->amount + $amount, 2),
                    'pending_amount' => round((float) $source->pending_amount + $amount, 2),
                ]);
            }
        }
    }

    /**
     * @return array{applications: list<array<string, mixed>>, shortfall: ?array<string, mixed>}
     */
    protected function legacyAllocationSnapshotFromShortfall(Payment $payment): array
    {
        $note = $payment->shortfall_allocation;

        if (! is_array($note)) {
            return ['applications' => [], 'shortfall' => null];
        }

        $applications = [];
        $shortfall = null;
        $action = $note['action'] ?? null;

        if ($action === PaymentShortfallAction::SurplusForward->value) {
            $sourceId = (int) ($note['source_installment_id'] ?? 0);
            $targets = $note['targets'] ?? [];
            $sourceApplied = round((float) ($payment->effectiveTuitionAmount() - collect($targets)->sum('amount')), 2);

            if ($sourceId > 0 && $sourceApplied > 0.01) {
                $applications[] = [
                    'installment_id' => $sourceId,
                    'label' => (string) ($note['source_label'] ?? ''),
                    'amount_applied' => $sourceApplied,
                ];
            }

            foreach ($targets as $target) {
                if (! is_array($target)) {
                    continue;
                }

                $applications[] = [
                    'installment_id' => (int) ($target['installment_id'] ?? 0),
                    'label' => (string) ($target['label'] ?? ''),
                    'amount_applied' => round((float) ($target['amount'] ?? 0), 2),
                ];
            }
        } elseif (in_array($action, [
            PaymentShortfallAction::NewInstallment->value,
            PaymentShortfallAction::CarryForward->value,
        ], true)) {
            $shortfall = $note;
            $sourceId = (int) ($note['source_installment_id'] ?? 0);
            $applied = round((float) $payment->effectiveTuitionAmount(), 2);

            if ($sourceId > 0 && $applied > 0.01) {
                $applications[] = [
                    'installment_id' => $sourceId,
                    'label' => (string) ($note['source_label'] ?? ''),
                    'amount_applied' => $applied,
                ];
            }
        } elseif ($payment->fee_installment_id) {
            $applications[] = [
                'installment_id' => (int) $payment->fee_installment_id,
                'label' => (string) ($payment->feeInstallment?->label ?? ''),
                'amount_applied' => round((float) $payment->effectiveTuitionAmount(), 2),
            ];
        }

        return [
            'applications' => $applications,
            'shortfall' => $shortfall,
        ];
    }

    /**
     * @param  array{action?: string, due_date?: string|null, label?: string|null}|null  $shortfallHandling
     * @return array{0: float, 1: ?array<string, mixed>, 2: float}
     */
    protected function processFlexibleInstallmentPayment(
        FeeInstallment $installment,
        float $remainingAmount,
        ?array $shortfallHandling = null,
    ): array {
        $installment->refresh();
        $pending = round((float) $installment->pending_amount, 2);

        if ($pending <= 0.01) {
            return [$remainingAmount, null, 0.0];
        }

        $amountToApply = min($remainingAmount, $pending);
        $newPending = round($pending - $amountToApply, 2);
        $newPaid = round((float) $installment->paid_amount + $amountToApply, 2);

        $installment->update([
            'paid_amount' => $newPaid,
            'pending_amount' => $newPending,
        ]);

        $remainingAmount = round($remainingAmount - $amountToApply, 2);
        $allocation = null;

        if ($newPending > 0.01 && $shortfallHandling !== null) {
            $allocation = $this->handleShortfall($installment, $newPending, $shortfallHandling);
        }

        return [$remainingAmount, $allocation, round($amountToApply, 2)];
    }

    /**
     * @param  array{action?: string, due_date?: string|null, label?: string|null}|null  $shortfallHandling
     * @return array<string, mixed>
     */
    protected function handleShortfall(
        FeeInstallment $current,
        float $shortfall,
        ?array $shortfallHandling,
    ): array {
        $shortfall = round($shortfall, 2);
        $action = PaymentShortfallAction::tryFrom((string) ($shortfallHandling['action'] ?? ''))
            ?? PaymentShortfallAction::CarryForward;

        if ($action === PaymentShortfallAction::NewInstallment) {
            $dueDate = filled($shortfallHandling['due_date'] ?? null)
                ? (string) $shortfallHandling['due_date']
                : now()->addMonth()->toDateString();
            $label = trim((string) ($shortfallHandling['label'] ?? ''))
                ?: PaymentShortfallHelper::suggestNewInstallmentLabel($current->fee_structure_id);

            $created = $this->insertShortfallInstallment($current, $shortfall, $label, $dueDate);
            $created->refresh();

            $current->update([
                'amount' => round((float) $current->paid_amount, 2),
                'pending_amount' => 0,
            ]);

            return [
                'amount' => $shortfall,
                'action' => PaymentShortfallAction::NewInstallment->value,
                'source_installment_id' => $current->id,
                'source_label' => $current->label,
                'target_installment_id' => $created->id,
                'target_label' => $created->label,
                'target_due_date' => $dueDate,
            ];
        }

        $nextInstallment = $this->nextPayableInstallment($current);

        if (! $nextInstallment) {
            throw ValidationException::withMessages([
                'shortfall_action' => 'No upcoming installment exists. Choose “Create new installment for balance” and set a due date.',
            ]);
        }

        $this->rollShortfallToNextInstallment($current, $shortfall);
        $nextInstallment->refresh();

        return [
            'amount' => $shortfall,
            'action' => PaymentShortfallAction::CarryForward->value,
            'source_installment_id' => $current->id,
            'source_label' => $current->label,
            'target_installment_id' => $nextInstallment->id,
            'target_label' => $nextInstallment->label,
            'target_due_date' => $nextInstallment->due_date?->toDateString(),
        ];
    }

    protected function insertShortfallInstallment(
        FeeInstallment $after,
        float $amount,
        string $label,
        string $dueDate,
    ): FeeInstallment {
        $sortOrder = (int) $after->sort_order + 1;

        FeeInstallment::query()
            ->where('fee_structure_id', $after->fee_structure_id)
            ->where('sort_order', '>=', $sortOrder)
            ->increment('sort_order');

        $created = $this->createInstallment($after->feeStructure, $label, $amount, $dueDate, $sortOrder);
        $this->syncInstallmentOrderAndLabels($after->feeStructure);

        return $created->fresh();
    }

    protected function rollShortfallToNextInstallment(FeeInstallment $current, float $shortfall): void
    {
        $shortfall = round($shortfall, 2);

        if ($shortfall <= 0.01) {
            return;
        }

        $nextInstallment = $this->nextPayableInstallment($current);

        if (! $nextInstallment) {
            return;
        }

        $nextInstallment->update([
            'amount' => round((float) $nextInstallment->amount + $shortfall, 2),
            'pending_amount' => round((float) $nextInstallment->pending_amount + $shortfall, 2),
        ]);

        $current->update([
            'amount' => round((float) $current->paid_amount, 2),
            'pending_amount' => 0,
        ]);
    }

    protected function nextPayableInstallment(FeeInstallment $current): ?FeeInstallment
    {
        return FeeInstallment::query()
            ->where('fee_structure_id', $current->fee_structure_id)
            ->where('pending_amount', '>', 0)
            ->where(function ($query) use ($current): void {
                $query->where('sort_order', '>', $current->sort_order)
                    ->orWhere(function ($query) use ($current): void {
                        $query->where('sort_order', $current->sort_order)
                            ->where('id', '>', $current->id);
                    });
            })
            ->orderBy('sort_order')
            ->orderBy('due_date')
            ->orderBy('id')
            ->first();
    }

    public function firstPayableInstallment(FeeStructure $feeStructure): ?FeeInstallment
    {
        return $this->payableInstallmentsForCollection($feeStructure)->first();
    }

    /**
     * Unpaid installments ordered for collection: overdue first, then soonest due.
     *
     * @return Collection<int, FeeInstallment>
     */
    public function payableInstallmentsForCollection(FeeStructure $feeStructure): Collection
    {
        $feeStructure->loadMissing('installments');

        return $feeStructure->installments
            ->filter(fn (FeeInstallment $row): bool => (float) $row->pending_amount > 0)
            ->sortBy(fn (FeeInstallment $row): array => [
                $row->isOverdue() ? 0 : 1,
                $row->due_date?->timestamp ?? PHP_INT_MAX,
                $row->sort_order,
                $row->id,
            ])
            ->values();
    }

    public function syncAfterFeeStructureChange(FeeStructure $feeStructure): void
    {
        $installments = $feeStructure->installments()->orderBy('sort_order')->get();

        if ($installments->isEmpty()) {
            return;
        }

        $netFee = round((float) $feeStructure->net_fee, 2);
        $paidTotal = round((float) $feeStructure->paid_amount, 2);
        $unpaid = $installments->filter(fn (FeeInstallment $row): bool => (float) $row->pending_amount > 0);

        if ($unpaid->isEmpty()) {
            return;
        }

        $remainingNet = round(max(0, $netFee - $paidTotal), 2);
        $unpaidAmountSum = round((float) $unpaid->sum('pending_amount'), 2);

        if ($unpaidAmountSum <= 0) {
            return;
        }

        $allocated = 0.0;
        $lastKey = $unpaid->keys()->last();

        foreach ($unpaid as $key => $installment) {
            $share = $key === $lastKey
                ? round($remainingNet - $allocated, 2)
                : round($remainingNet * ((float) $installment->pending_amount / $unpaidAmountSum), 2);

            $allocated += $share;
            $paidOnInstallment = round((float) $installment->paid_amount, 2);

            $installment->update([
                'amount' => round($paidOnInstallment + $share, 2),
                'pending_amount' => $share,
            ]);
        }
    }

    protected function createInstallment(
        FeeStructure $feeStructure,
        string $label,
        float $amount,
        ?string $dueDate,
        int $sortOrder,
    ): FeeInstallment {
        return FeeInstallment::query()->create([
            'fee_structure_id' => $feeStructure->id,
            'label' => $label,
            'amount' => $amount,
            'due_date' => $dueDate,
            'paid_amount' => 0,
            'pending_amount' => $amount,
            'sort_order' => $sortOrder,
        ]);
    }
}
