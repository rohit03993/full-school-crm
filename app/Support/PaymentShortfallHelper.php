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

    public static function surplusAmount(float $paymentAmount, ?FeeInstallment $installment): float
    {
        if (! $installment) {
            return 0.0;
        }

        $pending = round((float) $installment->pending_amount, 2);
        $paymentAmount = round($paymentAmount, 2);

        if ($pending <= 0 || $paymentAmount <= $pending + 0.01) {
            return 0.0;
        }

        return round($paymentAmount - $pending, 2);
    }

    /**
     * @param  Collection<int, FeeInstallment>  $payableInstallments
     */
    public static function surplusForwardPreview(
        float $paymentAmount,
        ?FeeInstallment $installment,
        Collection $payableInstallments,
    ): ?string {
        $surplus = self::surplusAmount($paymentAmount, $installment);

        if ($surplus <= 0 || ! $installment) {
            return null;
        }

        $ordered = $payableInstallments
            ->sortBy(fn (FeeInstallment $row): array => [
                $row->sort_order,
                $row->due_date?->toDateString() ?? '9999-12-31',
                $row->id,
            ])
            ->values();

        $startIndex = $ordered->search(fn (FeeInstallment $row): bool => $row->id === $installment->id);

        if ($startIndex === false) {
            return '₹'.number_format($surplus, 0).' extra will reduce upcoming installment(s).';
        }

        $remaining = $surplus;
        $targets = [];

        foreach ($ordered->slice($startIndex + 1) as $row) {
            if ($remaining <= 0.01) {
                break;
            }

            $pending = round((float) $row->pending_amount, 2);

            if ($pending <= 0) {
                continue;
            }

            $applied = min($remaining, $pending);
            $targets[] = $row->label.' (₹'.number_format($applied, 0).')';
            $remaining = round($remaining - $applied, 2);
        }

        if ($targets === []) {
            return '₹'.number_format($surplus, 0).' extra will reduce upcoming installment(s).';
        }

        return '₹'.number_format($surplus, 0).' extra after clearing '.$installment->label
            .' will be applied to: '.implode(', ', $targets).'.';
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
