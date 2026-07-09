<?php

namespace App\Services;

use App\Enums\FeeMiscChargeAdjustmentRequestStatus;
use App\Enums\FeeMiscChargeAdjustmentType;
use App\Models\FeeDiscountEntry;
use App\Models\FeeMiscChargeAdjustmentRequest;
use App\Support\CrmPagination;
use App\Support\FeeDiscountHistoryItem;
use Illuminate\Support\Collection;

class FeeDiscountHistoryService
{
    /**
     * @return array{
     *     tuition_discount_count: int,
     *     tuition_discount_total: float,
     *     misc_discount_count: int,
     *     misc_discount_total: float,
     *     misc_waive_count: int,
     *     misc_waive_total: float,
     *     combined_count: int,
     *     combined_total: float,
     * }
     */
    public function summary(): array
    {
        $tuitionCount = 0;
        $tuitionTotal = 0.0;
        $miscDiscountCount = 0;
        $miscDiscountTotal = 0.0;
        $miscWaiveCount = 0;
        $miscWaiveTotal = 0.0;

        $tuition = FeeDiscountEntry::query()
            ->where('amount', '<', 0)
            ->selectRaw('COUNT(*) as entry_count, COALESCE(SUM(ABS(amount)), 0) as entry_total')
            ->first();

        $tuitionCount = (int) ($tuition->entry_count ?? 0);
        $tuitionTotal = round((float) ($tuition->entry_total ?? 0), 2);

        if (FeeMiscChargeAdjustmentRequest::schemaReady()) {
            $miscDiscount = FeeMiscChargeAdjustmentRequest::query()
                ->where('status', FeeMiscChargeAdjustmentRequestStatus::Approved)
                ->where('type', FeeMiscChargeAdjustmentType::Discount)
                ->selectRaw('COUNT(*) as entry_count, COALESCE(SUM(COALESCE(applied_amount, discount_amount)), 0) as entry_total')
                ->first();

            $miscDiscountCount = (int) ($miscDiscount->entry_count ?? 0);
            $miscDiscountTotal = round((float) ($miscDiscount->entry_total ?? 0), 2);

            $miscWaive = FeeMiscChargeAdjustmentRequest::query()
                ->where('status', FeeMiscChargeAdjustmentRequestStatus::Approved)
                ->where('type', FeeMiscChargeAdjustmentType::WaiveOff)
                ->selectRaw('COUNT(*) as entry_count, COALESCE(SUM(applied_amount), 0) as entry_total')
                ->first();

            $miscWaiveCount = (int) ($miscWaive->entry_count ?? 0);
            $miscWaiveTotal = round((float) ($miscWaive->entry_total ?? 0), 2);
        }

        $combinedCount = $tuitionCount + $miscDiscountCount + $miscWaiveCount;
        $combinedTotal = round($tuitionTotal + $miscDiscountTotal + $miscWaiveTotal, 2);

        return [
            'tuition_discount_count' => $tuitionCount,
            'tuition_discount_total' => $tuitionTotal,
            'misc_discount_count' => $miscDiscountCount,
            'misc_discount_total' => $miscDiscountTotal,
            'misc_waive_count' => $miscWaiveCount,
            'misc_waive_total' => $miscWaiveTotal,
            'combined_count' => $combinedCount,
            'combined_total' => $combinedTotal,
        ];
    }

    /**
     * @return Collection<int, FeeDiscountHistoryItem>
     */
    public function recent(int $limit = CrmPagination::PER_PAGE): Collection
    {
        $items = collect();

        FeeDiscountEntry::query()
            ->where('amount', '<', 0)
            ->with([
                'grantedBy',
                'feeStructure.enrollment.student',
                'admission.student',
            ])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->each(function (FeeDiscountEntry $entry) use ($items): void {
                $student = $entry->feeStructure?->enrollment?->student
                    ?? $entry->admission?->student;

                $items->push(new FeeDiscountHistoryItem(
                    kind: 'tuition_discount',
                    kindLabel: 'Main fee discount',
                    label: 'Tuition fee plan',
                    amount: round(abs((float) $entry->amount), 2),
                    studentName: $student?->name ?? '—',
                    studentId: $student?->id,
                    actorName: $entry->grantedBy?->name ?? '—',
                    reason: $entry->reason,
                    occurredAt: $entry->created_at,
                    source: 'fee_discount_entry',
                ));
            });

        if (FeeMiscChargeAdjustmentRequest::schemaReady()) {
            FeeMiscChargeAdjustmentRequest::query()
                ->where('status', FeeMiscChargeAdjustmentRequestStatus::Approved)
                ->with([
                    'charge.feeStructure.enrollment.student',
                    'requestedBy',
                    'reviewedBy',
                ])
                ->orderByDesc('reviewed_at')
                ->limit($limit)
                ->get()
                ->each(function (FeeMiscChargeAdjustmentRequest $request) use ($items): void {
                    $charge = $request->charge;
                    $student = $charge?->feeStructure?->enrollment?->student;
                    $isWaive = $request->type === FeeMiscChargeAdjustmentType::WaiveOff;

                    $items->push(new FeeDiscountHistoryItem(
                        kind: $isWaive ? 'misc_waive_off' : 'misc_discount',
                        kindLabel: $isWaive ? 'Additional charge waive-off' : 'Additional charge discount',
                        label: $charge?->label ?? 'Additional charge',
                        amount: $this->resolvedAppliedAmount($request),
                        studentName: $student?->name ?? '—',
                        studentId: $student?->id,
                        actorName: $request->reviewedBy?->name ?? $request->requestedBy?->name ?? '—',
                        reason: $request->reason,
                        occurredAt: $request->reviewed_at ?? $request->created_at,
                        source: 'misc_charge_adjustment',
                    ));
                });
        }

        return $items
            ->sortByDesc(fn (FeeDiscountHistoryItem $item): int => $item->occurredAt->timestamp)
            ->take($limit)
            ->values();
    }

    public function resolvedAppliedAmount(FeeMiscChargeAdjustmentRequest $request): float
    {
        if ($request->applied_amount !== null) {
            return round((float) $request->applied_amount, 2);
        }

        if ($request->type === FeeMiscChargeAdjustmentType::Discount) {
            return round((float) ($request->discount_amount ?? 0), 2);
        }

        return 0.0;
    }
}
