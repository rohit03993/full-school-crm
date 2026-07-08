<?php

namespace App\Services;

use App\Enums\FeeMiscChargeKind;
use App\Enums\FeeMiscChargeStatus;
use App\Enums\PaymentMode;
use App\Models\Admission;
use App\Models\FeeMiscCharge;
use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\User;
use App\Support\FeeSettings;
use Illuminate\Support\Facades\DB;

class OnlineAllowanceGstService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    public function isEnabled(): bool
    {
        return FeeSettings::onlineAllowanceGstEnabled();
    }

    public function requiresAllowanceSplit(FeeStructure $feeStructure): bool
    {
        return $this->isEnabled() && ! $feeStructure->hasOnlineAllowancePlan();
    }

    public function assertOnlineTuitionAllowed(FeeStructure $feeStructure, PaymentMode $mode): void
    {
        if (! $this->requiresAllowanceSplit($feeStructure)) {
            return;
        }

        if (! in_array($mode, [PaymentMode::Online, PaymentMode::Upi], true)) {
            return;
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'payment_mode' => 'Set the cash vs online split in Adjust Fees before recording online or UPI tuition payments.',
        ]);
    }

    /**
     * @return array{splits_cleared: int, gst_charges_removed: int}
     */
    public function cleanupWhenDisabled(User $staff): array
    {
        return DB::transaction(function () use ($staff): array {
            $splitsCleared = FeeStructure::query()
                ->where(function ($query): void {
                    $query->whereNotNull('planned_cash_amount')
                        ->orWhereNotNull('planned_online_amount');
                })
                ->update([
                    'planned_cash_amount' => null,
                    'planned_online_amount' => null,
                ]);

            Admission::query()
                ->where(function ($query): void {
                    $query->whereNotNull('planned_cash_amount')
                        ->orWhereNotNull('planned_online_amount');
                })
                ->update([
                    'planned_cash_amount' => null,
                    'planned_online_amount' => null,
                ]);

            $gstCharges = FeeMiscCharge::query()
                ->where('kind', FeeMiscChargeKind::GstPenalty)
                ->where('status', FeeMiscChargeStatus::Pending)
                ->where('paid_amount', '<=', 0)
                ->get();

            foreach ($gstCharges as $charge) {
                $charge->update(['status' => FeeMiscChargeStatus::Cancelled]);
            }

            $this->audit->log(
                action: 'GST Feature Disabled Cleanup',
                auditable: null,
                newValues: [
                    'splits_cleared' => $splitsCleared,
                    'gst_charges_removed' => $gstCharges->count(),
                ],
                user: $staff,
            );

            return [
                'splits_cleared' => $splitsCleared,
                'gst_charges_removed' => $gstCharges->count(),
            ];
        });
    }

    public function applyAfterTuitionPayment(
        Payment $payment,
        FeeStructure $feeStructure,
        float $tuitionAmount,
    ): ?FeeMiscCharge {
        if (! $this->isEnabled()) {
            return null;
        }

        if (! $feeStructure->hasOnlineAllowancePlan()) {
            return null;
        }

        if ($payment->fee_misc_charge_id !== null) {
            return null;
        }

        if (! in_array($payment->payment_mode, [PaymentMode::Online, PaymentMode::Upi], true)) {
            return null;
        }

        $tuitionAmount = round($tuitionAmount, 2);

        if ($tuitionAmount <= 0) {
            return null;
        }

        $onlineAllowance = round((float) $feeStructure->planned_online_amount, 2);
        $previousOnline = $this->onlineTuitionPaidTotal($feeStructure, $payment->id);
        $currentOnline = round($previousOnline + $tuitionAmount, 2);

        $previousExcess = max(0, round($previousOnline - $onlineAllowance, 2));
        $currentExcess = max(0, round($currentOnline - $onlineAllowance, 2));
        $incrementalExcess = round($currentExcess - $previousExcess, 2);

        if ($incrementalExcess <= 0) {
            return null;
        }

        $gstRate = FeeSettings::gstPenaltyPercentage();
        $gstPenaltyAmount = round($incrementalExcess * ($gstRate / 100), 2);

        if ($gstPenaltyAmount <= 0) {
            return null;
        }

        $sortOrder = (int) $feeStructure->miscCharges()->max('sort_order') + 1;

        return FeeMiscCharge::query()->create([
            'fee_structure_id' => $feeStructure->id,
            'label' => 'GST penalty on online overage (₹'
                .number_format($incrementalExcess, 2).' excess × '.$gstRate.'% = ₹'
                .number_format($gstPenaltyAmount, 2).')',
            'amount' => $gstPenaltyAmount,
            'kind' => FeeMiscChargeKind::GstPenalty,
            'status' => FeeMiscChargeStatus::Pending,
            'due_date' => now()->toDateString(),
            'sort_order' => $sortOrder,
        ]);
    }

    public function onlineTuitionPaidTotal(FeeStructure $feeStructure, ?int $exceptPaymentId = null): float
    {
        $query = Payment::query()
            ->where('fee_structure_id', $feeStructure->id)
            ->whereNull('fee_misc_charge_id')
            ->whereIn('payment_mode', [PaymentMode::Online->value, PaymentMode::Upi->value]);

        if ($exceptPaymentId) {
            $query->where('id', '!=', $exceptPaymentId);
        }

        $payments = $query->get(['amount', 'tuition_amount']);

        return round((float) $payments->sum(
            fn (Payment $payment): float => (float) ($payment->tuition_amount ?? $payment->amount),
        ), 2);
    }

    public function assertAllowanceSplitValid(float $netFee, float $cash, float $online): void
    {
        $netFee = round($netFee, 2);
        $cash = round($cash, 2);
        $online = round($online, 2);

        if ($cash < 0 || $online < 0) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'planned_cash_amount' => 'Cash and online amounts cannot be negative.',
            ]);
        }

        if (abs(($cash + $online) - $netFee) > 0.01) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'planned_cash_amount' => 'Cash plus online must equal the net tuition fee (₹'.number_format($netFee, 2).').',
            ]);
        }
    }
}
