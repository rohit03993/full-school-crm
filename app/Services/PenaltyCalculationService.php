<?php

namespace App\Services;

use App\Enums\FeePenaltyStatus;
use App\Enums\FeePenaltyType;
use App\Models\FeeInstallment;
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
            $penalty = $this->processInstallmentPenalty($installment, $today);

            if ($penalty) {
                $processed++;
                $totalPenalty += (float) $penalty->penalty_amount;
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

    public function processInstallmentPenalty(FeeInstallment $installment, Carbon $today): ?FeePenalty
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

        return FeePenalty::query()->updateOrCreate(
            [
                'fee_installment_id' => $installment->id,
                'penalty_type' => FeePenaltyType::LateFee,
                'status' => FeePenaltyStatus::Pending,
            ],
            [
                'student_id' => $student->id,
                'fee_structure_id' => $feeStructure->id,
                'penalty_date' => $today->toDateString(),
                'base_amount' => $baseAmount,
                'penalty_amount' => $penaltyAmount,
                'days_late' => $daysLate,
                'description' => "Late fee for {$daysLate} day(s) after grace (due {$installment->due_date->format('d M Y')})",
            ],
        );
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

        return $penalty->fresh(['feeInstallment', 'waivedBy']);
    }

    public function applyPendingPayments(FeeStructure $feeStructure, float $amount): void
    {
        $remaining = round($amount, 2);

        if ($remaining <= 0) {
            return;
        }

        $penalties = $feeStructure->penalties()
            ->where('status', FeePenaltyStatus::Pending)
            ->orderBy('penalty_date')
            ->orderBy('id')
            ->get();

        foreach ($penalties as $penalty) {
            if ($remaining <= 0.01) {
                break;
            }

            $due = round((float) $penalty->penalty_amount, 2);

            if ($remaining + 0.01 < $due) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'amount' => 'Late fee payments must cover each penalty in full.',
                ]);
            }

            $penalty->update(['status' => FeePenaltyStatus::Paid]);
            $remaining = round($remaining - $due, 2);
        }
    }
}
