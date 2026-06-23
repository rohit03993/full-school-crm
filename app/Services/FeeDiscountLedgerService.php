<?php

namespace App\Services;

use App\Models\Admission;
use App\Models\FeeDiscountEntry;
use App\Models\FeeStructure;
use App\Models\User;

class FeeDiscountLedgerService
{
    public function recordAdmissionChange(
        Admission $admission,
        float $previousTotal,
        float $newTotal,
        ?User $staff,
        ?string $reason = null,
    ): void {
        $delta = round($newTotal - $previousTotal, 2);

        if ($delta === 0.0) {
            return;
        }

        $admission->loadMissing('enrollment.feeStructure');

        FeeDiscountEntry::query()->create([
            'admission_id' => $admission->id,
            'fee_structure_id' => $admission->enrollment?->feeStructure?->id,
            'amount' => $delta,
            'total_after' => round($newTotal, 2),
            'reason' => $reason ?: ($delta > 0 ? 'Additional discount' : 'Discount reduced'),
            'granted_by_user_id' => $staff?->id,
        ]);
    }

    public function recordFeeStructureChange(
        FeeStructure $feeStructure,
        float $previousTotal,
        float $newTotal,
        User $admin,
        string $reason,
    ): void {
        $delta = round($newTotal - $previousTotal, 2);

        if ($delta === 0.0) {
            return;
        }

        $admissionId = $feeStructure->enrollment?->admission_id;

        $feeStructure->loadMissing('enrollment');

        FeeDiscountEntry::query()->create([
            'admission_id' => $admissionId,
            'fee_structure_id' => $feeStructure->id,
            'amount' => $delta,
            'total_after' => round($newTotal, 2),
            'reason' => $reason,
            'granted_by_user_id' => $admin->id,
        ]);
    }

    public function linkAdmissionEntriesToFeeStructure(Admission $admission, FeeStructure $feeStructure): void
    {
        FeeDiscountEntry::query()
            ->where('admission_id', $admission->id)
            ->whereNull('fee_structure_id')
            ->update(['fee_structure_id' => $feeStructure->id]);
    }
}
