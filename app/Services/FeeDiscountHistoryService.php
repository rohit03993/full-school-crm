<?php

namespace App\Services;

use App\Enums\FeeMiscChargeAdjustmentRequestStatus;
use App\Enums\FeeMiscChargeAdjustmentType;
use App\Models\FeeDiscountEntry;
use App\Models\FeeMiscChargeAdjustmentRequest;
use App\Models\Student;
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

    /**
     * @return array{
     *     approved_total: float,
     *     pending_total: float,
     *     approved_count: int,
     *     pending_count: int,
     * }
     */
    public function studentSummary(Student $student): array
    {
        $feeStructureId = $student->activeEnrollment?->feeStructure?->id;
        $admissionId = $student->activeEnrollment?->admission_id;

        if (! $feeStructureId && ! $admissionId) {
            return [
                'approved_total' => 0.0,
                'pending_total' => 0.0,
                'approved_count' => 0,
                'pending_count' => 0,
            ];
        }

        $tuitionQuery = FeeDiscountEntry::query()->where('amount', '<', 0);

        if ($feeStructureId) {
            $tuitionQuery->where('fee_structure_id', $feeStructureId);
        } else {
            $tuitionQuery->where('admission_id', $admissionId);
        }

        $tuition = $tuitionQuery
            ->selectRaw('COUNT(*) as entry_count, COALESCE(SUM(ABS(amount)), 0) as entry_total')
            ->first();

        $approvedTotal = round((float) ($tuition->entry_total ?? 0), 2);
        $approvedCount = (int) ($tuition->entry_count ?? 0);
        $pendingTotal = 0.0;
        $pendingCount = 0;

        if (FeeMiscChargeAdjustmentRequest::schemaReady() && $feeStructureId) {
            $miscApproved = FeeMiscChargeAdjustmentRequest::query()
                ->where('status', FeeMiscChargeAdjustmentRequestStatus::Approved)
                ->whereHas('charge', fn ($query) => $query->where('fee_structure_id', $feeStructureId))
                ->get();

            foreach ($miscApproved as $request) {
                $approvedTotal = round($approvedTotal + $this->resolvedAppliedAmount($request), 2);
                $approvedCount++;
            }

            $miscPending = FeeMiscChargeAdjustmentRequest::query()
                ->where('status', FeeMiscChargeAdjustmentRequestStatus::Pending)
                ->whereHas('charge', fn ($query) => $query->where('fee_structure_id', $feeStructureId))
                ->with('charge')
                ->get();

            foreach ($miscPending as $request) {
                $pendingTotal = round($pendingTotal + $this->pendingRequestAmount($request), 2);
                $pendingCount++;
            }
        }

        return [
            'approved_total' => $approvedTotal,
            'pending_total' => $pendingTotal,
            'approved_count' => $approvedCount,
            'pending_count' => $pendingCount,
        ];
    }

    /**
     * @return Collection<int, FeeDiscountHistoryItem>
     */
    public function studentTimeline(Student $student, int $limit = 50): Collection
    {
        $feeStructureId = $student->activeEnrollment?->feeStructure?->id;
        $admissionId = $student->activeEnrollment?->admission_id;

        if (! $feeStructureId && ! $admissionId) {
            return collect();
        }

        $items = collect();

        $tuitionQuery = FeeDiscountEntry::query()
            ->where('amount', '<', 0)
            ->with(['grantedBy']);

        if ($feeStructureId) {
            $tuitionQuery->where('fee_structure_id', $feeStructureId);
        } else {
            $tuitionQuery->where('admission_id', $admissionId);
        }

        $tuitionQuery
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->each(function (FeeDiscountEntry $entry) use ($items, $student): void {
                $items->push(new FeeDiscountHistoryItem(
                    kind: 'tuition_discount',
                    kindLabel: 'Main fee discount',
                    label: 'Tuition fee plan',
                    amount: round(abs((float) $entry->amount), 2),
                    studentName: $student->name,
                    studentId: $student->id,
                    actorName: $entry->grantedBy?->name ?? '—',
                    reason: $entry->reason,
                    occurredAt: $entry->created_at,
                    source: 'fee_discount_entry',
                    status: 'approved',
                    statusLabel: 'Approved',
                ));
            });

        if (FeeMiscChargeAdjustmentRequest::schemaReady() && $feeStructureId) {
            FeeMiscChargeAdjustmentRequest::query()
                ->whereHas('charge', fn ($query) => $query->where('fee_structure_id', $feeStructureId))
                ->whereIn('status', [
                    FeeMiscChargeAdjustmentRequestStatus::Approved,
                    FeeMiscChargeAdjustmentRequestStatus::Pending,
                ])
                ->with(['charge', 'requestedBy', 'reviewedBy'])
                ->orderByDesc('created_at')
                ->limit($limit)
                ->get()
                ->each(function (FeeMiscChargeAdjustmentRequest $request) use ($items, $student): void {
                    $charge = $request->charge;
                    $isWaive = $request->type === FeeMiscChargeAdjustmentType::WaiveOff;
                    $isPending = $request->status === FeeMiscChargeAdjustmentRequestStatus::Pending;

                    $items->push(new FeeDiscountHistoryItem(
                        kind: $isWaive ? 'misc_waive_off' : 'misc_discount',
                        kindLabel: $isWaive ? 'Additional charge waive-off' : 'Additional charge discount',
                        label: $charge?->label ?? 'Additional charge',
                        amount: $isPending
                            ? $this->pendingRequestAmount($request)
                            : $this->resolvedAppliedAmount($request),
                        studentName: $student->name,
                        studentId: $student->id,
                        actorName: $isPending
                            ? ($request->requestedBy?->name ?? '—')
                            : ($request->reviewedBy?->name ?? $request->requestedBy?->name ?? '—'),
                        reason: $request->reason,
                        occurredAt: $isPending
                            ? $request->created_at
                            : ($request->reviewed_at ?? $request->created_at),
                        source: 'misc_charge_adjustment',
                        status: $isPending ? 'pending' : 'approved',
                        statusLabel: $isPending ? 'Pending' : 'Approved',
                    ));
                });
        }

        return $items
            ->sortByDesc(fn (FeeDiscountHistoryItem $item): int => $item->occurredAt->timestamp)
            ->take($limit)
            ->values();
    }

    public function pendingRequestAmount(FeeMiscChargeAdjustmentRequest $request): float
    {
        if ($request->type === FeeMiscChargeAdjustmentType::Discount) {
            return round((float) ($request->discount_amount ?? 0), 2);
        }

        return round((float) ($request->charge?->pendingAmount() ?? 0), 2);
    }
}
