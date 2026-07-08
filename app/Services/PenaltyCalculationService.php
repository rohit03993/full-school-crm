<?php

namespace App\Services;

use App\Enums\FeeMiscChargeKind;
use App\Enums\FeeMiscChargeStatus;
use App\Enums\FeePenaltyStatus;
use App\Enums\FeePenaltyType;
use App\Models\FeeInstallment;
use App\Models\FeeMiscCharge;
use App\Models\FeePenalty;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PenaltyCalculationService
{
    public function graceDays(): int
    {
        return max(0, (int) config('fees.late_fee.grace_days', 7));
    }

    public function dailyRate(): float
    {
        return max(0, (float) config('fees.late_fee.daily_rate', 0.0015));
    }

    public function isEnabled(): bool
    {
        return (bool) config('fees.late_fee.enabled', true);
    }

    public function calculateLateFee(float $baseAmount, int $daysLate): float
    {
        if ($daysLate <= 0 || $baseAmount <= 0) {
            return 0.0;
        }

        return round($baseAmount * $this->dailyRate() * $daysLate, 2);
    }

    /**
     * @return array{processed: int, total_penalty: float}
     */
    public function processOverdueInstallments(?Carbon $asOf = null): array
    {
        if (! $this->isEnabled()) {
            return ['processed' => 0, 'total_penalty' => 0.0];
        }

        $this->migrateLegacyPenaltiesToMiscCharges();

        $today = ($asOf ?? now())->copy()->startOfDay();
        $graceCutoff = $today->copy()->subDays($this->graceDays())->toDateString();
        $processed = 0;
        $totalPenalty = 0.0;

        $installments = FeeInstallment::query()
            ->with('feeStructure.enrollment.student')
            ->where('pending_amount', '>', 0)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $graceCutoff)
            ->get();

        foreach ($installments as $installment) {
            $charge = $this->processInstallmentPenalty($installment, $today);

            if ($charge) {
                $processed++;
                $totalPenalty += (float) $charge->pendingAmount();
            }
        }

        Log::info('crm:process-late-fees completed', [
            'processed' => $processed,
            'total_penalty' => $totalPenalty,
        ]);

        return [
            'processed' => $processed,
            'total_penalty' => round($totalPenalty, 2),
        ];
    }

    public function processInstallmentPenalty(FeeInstallment $installment, Carbon $today): ?FeeMiscCharge
    {
        if (! $installment->due_date) {
            return null;
        }

        $gracePeriodEnd = $installment->due_date->copy()->addDays($this->graceDays());
        $daysLate = (int) $gracePeriodEnd->diffInDays($today);

        if ($daysLate <= 0) {
            return null;
        }

        $baseAmount = round((float) $installment->pending_amount, 2);
        $penaltyAmount = $this->calculateLateFee($baseAmount, $daysLate);

        if ($penaltyAmount <= 0) {
            return null;
        }

        $feeStructure = $installment->feeStructure;
        $student = $feeStructure?->enrollment?->student;

        if (! $feeStructure || ! $student) {
            return null;
        }

        $existing = FeeMiscCharge::query()
            ->where('fee_installment_id', $installment->id)
            ->where('kind', FeeMiscChargeKind::LateFeePenalty)
            ->where('status', '!=', FeeMiscChargeStatus::Cancelled)
            ->first();

        $paidAmount = round((float) ($existing?->paid_amount ?? 0), 2);
        $status = match (true) {
            $paidAmount <= 0 => FeeMiscChargeStatus::Pending,
            $paidAmount + 0.01 >= $penaltyAmount => FeeMiscChargeStatus::Paid,
            default => FeeMiscChargeStatus::Partial,
        };

        $label = sprintf(
            'Late fee penalty — %s (%d day(s) after grace · installment due %s · ₹%s pending × %s%%/day)',
            $installment->label,
            $daysLate,
            $installment->due_date->format('d M Y'),
            number_format($baseAmount, 2),
            number_format($this->dailyRate() * 100, 2),
        );

        $charge = FeeMiscCharge::query()->updateOrCreate(
            [
                'fee_installment_id' => $installment->id,
                'kind' => FeeMiscChargeKind::LateFeePenalty,
            ],
            [
                'fee_structure_id' => $feeStructure->id,
                'label' => $label,
                'amount' => $penaltyAmount,
                'paid_amount' => $paidAmount,
                'status' => $status,
                'due_date' => $today->toDateString(),
                'sort_order' => (int) ($existing?->sort_order ?? ((int) $feeStructure->miscCharges()->max('sort_order') + 1)),
            ],
        );

        if ($charge->wasRecentlyCreated) {
            app(AccountingLedgerService::class)->postLateFeeMiscAccrual($charge);
        }

        $this->retireLegacyPenaltyRecord($installment);

        return $charge->fresh();
    }

    public function waive(FeePenalty $penalty, User $admin, string $reason): FeePenalty
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'reason' => 'A reason is required to waive a late fee.',
            ]);
        }

        if ($penalty->status !== FeePenaltyStatus::Pending) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'penalty' => 'Only pending late fees can be waived.',
            ]);
        }

        $penalty->update([
            'status' => FeePenaltyStatus::Waived,
            'waived_by_user_id' => $admin->id,
            'waived_reason' => $reason,
        ]);

        if ($penalty->fee_installment_id) {
            FeeMiscCharge::query()
                ->where('fee_installment_id', $penalty->fee_installment_id)
                ->where('kind', FeeMiscChargeKind::LateFeePenalty)
                ->whereIn('status', [FeeMiscChargeStatus::Pending, FeeMiscChargeStatus::Partial])
                ->update([
                    'status' => FeeMiscChargeStatus::Cancelled,
                ]);
        }

        return $penalty->fresh(['feeInstallment', 'waivedBy']);
    }

    protected function migrateLegacyPenaltiesToMiscCharges(): void
    {
        FeePenalty::query()
            ->where('penalty_type', FeePenaltyType::LateFee)
            ->where('status', FeePenaltyStatus::Pending)
            ->with(['feeInstallment', 'feeStructure'])
            ->orderBy('id')
            ->each(function (FeePenalty $penalty): void {
                $installment = $penalty->feeInstallment;

                if (! $installment || ! $penalty->feeStructure) {
                    return;
                }

                $exists = FeeMiscCharge::query()
                    ->where('fee_installment_id', $installment->id)
                    ->where('kind', FeeMiscChargeKind::LateFeePenalty)
                    ->where('status', '!=', FeeMiscChargeStatus::Cancelled)
                    ->exists();

                if (! $exists) {
                    $amount = round((float) $penalty->penalty_amount, 2);

                    FeeMiscCharge::query()->create([
                        'fee_structure_id' => $penalty->fee_structure_id,
                        'fee_installment_id' => $installment->id,
                        'label' => $penalty->description
                            ?? sprintf('Late fee penalty — %s', $installment->label),
                        'amount' => $amount,
                        'paid_amount' => 0,
                        'kind' => FeeMiscChargeKind::LateFeePenalty,
                        'status' => FeeMiscChargeStatus::Pending,
                        'due_date' => $penalty->penalty_date,
                        'sort_order' => (int) $penalty->feeStructure->miscCharges()->max('sort_order') + 1,
                    ]);
                }

                $penalty->update([
                    'status' => FeePenaltyStatus::Waived,
                    'waived_reason' => 'Moved to misc charge for collection',
                ]);
            });
    }

    protected function retireLegacyPenaltyRecord(FeeInstallment $installment): void
    {
        FeePenalty::query()
            ->where('fee_installment_id', $installment->id)
            ->where('penalty_type', FeePenaltyType::LateFee)
            ->where('status', FeePenaltyStatus::Pending)
            ->update([
                'status' => FeePenaltyStatus::Waived,
                'waived_reason' => 'Moved to misc charge for collection',
            ]);
    }
}
