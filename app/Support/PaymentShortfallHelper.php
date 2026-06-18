<?php

namespace App\Support;

use App\Models\FeeInstallment;
use Illuminate\Support\Collection;

class PaymentShortfallHelper
{
    /**
     * @param  Collection<int, FeeInstallment>  $payableInstallments
     */
    public static function resolveInstallment(
        mixed $installmentId,
        Collection $payableInstallments,
        ?FeeInstallment $defaultInstallment,
    ): ?FeeInstallment {
        if ($payableInstallments->isEmpty()) {
            return null;
        }

        if (filled($installmentId)) {
            return $payableInstallments->firstWhere('id', (int) $installmentId);
        }

        return $defaultInstallment;
    }

    public static function shortfallAmount(float $paymentAmount, ?FeeInstallment $installment): float
    {
        if (! $installment) {
            return 0.0;
        }

        $pending = round((float) $installment->pending_amount, 2);
        $paymentAmount = round($paymentAmount, 2);

        if ($pending <= 0 || $paymentAmount >= $pending - 0.01) {
            return 0.0;
        }

        return round($pending - $paymentAmount, 2);
    }

    public static function hasNextPayableInstallment(?FeeInstallment $installment): bool
    {
        if (! $installment) {
            return false;
        }

        return FeeInstallment::query()
            ->where('fee_structure_id', $installment->fee_structure_id)
            ->where('pending_amount', '>', 0)
            ->where(function ($query) use ($installment): void {
                $query->where('sort_order', '>', $installment->sort_order)
                    ->orWhere(function ($query) use ($installment): void {
                        $query->where('sort_order', $installment->sort_order)
                            ->where('id', '>', $installment->id);
                    });
            })
            ->exists();
    }

    public static function suggestNewInstallmentLabel(int $feeStructureId): string
    {
        $count = FeeInstallment::query()
            ->where('fee_structure_id', $feeStructureId)
            ->count();

        return FeePlanCalculator::installmentLabel($count + 1);
    }
}
