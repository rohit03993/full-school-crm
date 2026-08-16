<?php

namespace App\Services;

use App\Enums\CrmPermission;
use App\Enums\PaymentCancellationRequestStatus;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\PaymentCancellationRequest;
use App\Models\User;
use App\Support\CrmAccess;
use App\Support\CrmCacheInvalidator;
use App\Support\CrmNavBadges;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PaymentCancellationService
{
    public function __construct(
        protected AuditService $audit,
        protected FeeInstallmentService $installments,
        protected FeeMiscChargeService $miscCharges,
        protected OnlineAllowanceGstService $onlineAllowanceGst,
        protected ReceiptService $receipts,
        protected AccountingLedgerService $ledger,
    ) {}

    public function submitRequest(Payment $payment, User $staff, string $reason): PaymentCancellationRequest
    {
        if (! PaymentCancellationRequest::schemaReady()) {
            throw ValidationException::withMessages([
                'payment' => 'Payment cancellation requests are not available yet. Run database migrations (php artisan migrate).',
            ]);
        }

        $this->assertCanRequest($staff);
        $this->assertPaymentCancellable($payment);

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required to request payment cancellation.',
            ]);
        }

        if ($this->hasPendingRequest($payment)) {
            throw ValidationException::withMessages([
                'payment' => 'A cancellation request is already pending for this payment.',
            ]);
        }

        $request = PaymentCancellationRequest::query()->create([
            'payment_id' => $payment->id,
            'requested_by_user_id' => $staff->id,
            'reason' => $reason,
            'status' => PaymentCancellationRequestStatus::Pending,
        ]);

        $this->audit->log(
            action: 'Payment Cancel Requested',
            auditable: $request,
            newValues: [
                'payment_id' => $payment->id,
                'receipt_number' => $payment->receipt_number,
                'amount' => (float) $payment->amount,
                'reason' => $reason,
            ],
            user: $staff,
        );

        CrmNavBadges::flushPaymentCancellationBadgeCache();

        return $request->fresh(['payment.student', 'payment.feeStructure.enrollment', 'requestedBy']);
    }

    public function approve(PaymentCancellationRequest $request, User $admin, ?string $reviewNotes = null): PaymentCancellationRequest
    {
        $this->assertCanReview($admin);

        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be approved.',
            ]);
        }

        $payment = $request->payment()->firstOrFail();
        $this->assertPaymentCancellable($payment);

        return DB::transaction(function () use ($request, $admin, $reviewNotes, $payment): PaymentCancellationRequest {
            $locked = Payment::query()
                ->whereKey($payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertPaymentCancellable($locked);

            $feeStructure = $locked->feeStructure()->lockForUpdate()->firstOrFail();

            if ($locked->isMiscPayment()) {
                $charge = $locked->feeMiscCharge()->lockForUpdate()->firstOrFail();
                $this->miscCharges->reversePayment($charge, (float) $locked->amount);
            } else {
                $tuition = $locked->effectiveTuitionAmount();
                $newPaid = round(max(0, (float) $feeStructure->paid_amount - $tuition), 2);
                $newPending = round(max(0, (float) $feeStructure->net_fee - $newPaid), 2);

                $feeStructure->update([
                    'paid_amount' => $newPaid,
                    'pending_amount' => $newPending,
                ]);

                if ($feeStructure->installments()->exists()) {
                    $this->installments->reversePaymentAllocation($locked);
                }
            }

            $locked->update([
                'status' => PaymentStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by_user_id' => $admin->id,
                'cancel_reason' => $request->reason,
            ]);

            $request->update([
                'status' => PaymentCancellationRequestStatus::Approved,
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now(),
                'review_notes' => filled($reviewNotes) ? trim($reviewNotes) : null,
            ]);

            PaymentCancellationRequest::query()
                ->where('payment_id', $locked->id)
                ->where('status', PaymentCancellationRequestStatus::Pending)
                ->whereKeyNot($request->id)
                ->update([
                    'status' => PaymentCancellationRequestStatus::Rejected,
                    'reviewed_by_user_id' => $admin->id,
                    'reviewed_at' => now(),
                    'review_notes' => 'Auto-rejected because another cancellation was approved.',
                ]);

            $locked = $locked->fresh(['cancelledBy', 'addedBy.staffProfile', 'feeStructure.enrollment.course', 'student']);

            if (! $locked->isMiscPayment()) {
                $this->onlineAllowanceGst->syncUnpaidGstAfterPaymentCancellation(
                    $feeStructure->fresh(),
                    $admin,
                );
            }

            $this->receipts->generateForPayment($locked, $admin, regenerate: true);
            $this->ledger->postPaymentCancellation($locked, $admin);

            $this->audit->log(
                action: 'Payment Cancel Approved',
                auditable: $request,
                newValues: [
                    'payment_id' => $locked->id,
                    'receipt_number' => $locked->receipt_number,
                    'amount' => (float) $locked->amount,
                    'review_notes' => $request->review_notes,
                ],
                user: $admin,
            );

            $this->audit->log(
                action: 'Payment Cancelled',
                auditable: $locked,
                newValues: [
                    'receipt_number' => $locked->receipt_number,
                    'amount' => (float) $locked->amount,
                    'cancel_reason' => $locked->cancel_reason,
                    'cancelled_by_user_id' => $admin->id,
                ],
                user: $admin,
            );

            CrmNavBadges::flushPaymentCancellationBadgeCache();
            CrmCacheInvalidator::afterPayment();

            return $request->fresh([
                'payment.student',
                'payment.feeStructure.enrollment.course',
                'payment.cancelledBy',
                'requestedBy',
                'reviewedBy',
            ]);
        });
    }

    public function reject(PaymentCancellationRequest $request, User $admin, ?string $reviewNotes = null): PaymentCancellationRequest
    {
        $this->assertCanReview($admin);

        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be rejected.',
            ]);
        }

        $reviewNotes = filled($reviewNotes) ? trim((string) $reviewNotes) : null;

        $request->update([
            'status' => PaymentCancellationRequestStatus::Rejected,
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);

        $this->audit->log(
            action: 'Payment Cancel Rejected',
            auditable: $request,
            newValues: [
                'payment_id' => $request->payment_id,
                'review_notes' => $reviewNotes,
            ],
            user: $admin,
        );

        CrmNavBadges::flushPaymentCancellationBadgeCache();

        return $request->fresh(['payment.student', 'payment.feeStructure.enrollment', 'requestedBy', 'reviewedBy']);
    }

    public function hasPendingRequest(Payment $payment): bool
    {
        if (! PaymentCancellationRequest::schemaReady()) {
            return false;
        }

        return PaymentCancellationRequest::query()
            ->where('payment_id', $payment->id)
            ->where('status', PaymentCancellationRequestStatus::Pending)
            ->exists();
    }

    /**
     * @return Collection<int, PaymentCancellationRequest>
     */
    public function pendingRequests(): Collection
    {
        if (! PaymentCancellationRequest::schemaReady()) {
            return collect();
        }

        return PaymentCancellationRequest::query()
            ->where('status', PaymentCancellationRequestStatus::Pending)
            ->with([
                'payment.student',
                'payment.feeStructure.enrollment.course',
                'payment.feeMiscCharge',
                'payment.addedBy',
                'requestedBy',
            ])
            ->orderBy('created_at')
            ->get();
    }

    public function pendingCount(): int
    {
        if (! PaymentCancellationRequest::schemaReady()) {
            return 0;
        }

        return PaymentCancellationRequest::query()
            ->where('status', PaymentCancellationRequestStatus::Pending)
            ->count();
    }

    /**
     * @return array{
     *     pending_count: int,
     *     approved_count: int,
     *     approved_total: float,
     *     rejected_count: int,
     *     reviewed_count: int,
     * }
     */
    public function summary(): array
    {
        if (! PaymentCancellationRequest::schemaReady()) {
            return [
                'pending_count' => 0,
                'approved_count' => 0,
                'approved_total' => 0.0,
                'rejected_count' => 0,
                'reviewed_count' => 0,
            ];
        }

        $pendingCount = PaymentCancellationRequest::query()
            ->where('status', PaymentCancellationRequestStatus::Pending)
            ->count();

        $approved = PaymentCancellationRequest::query()
            ->where('payment_cancellation_requests.status', PaymentCancellationRequestStatus::Approved)
            ->leftJoin('payments', 'payments.id', '=', 'payment_cancellation_requests.payment_id')
            ->selectRaw('COUNT(payment_cancellation_requests.id) as entry_count, COALESCE(SUM(payments.amount), 0) as entry_total')
            ->first();

        $rejectedCount = PaymentCancellationRequest::query()
            ->where('status', PaymentCancellationRequestStatus::Rejected)
            ->count();

        $approvedCount = (int) ($approved->entry_count ?? 0);
        $approvedTotal = round((float) ($approved->entry_total ?? 0), 2);

        return [
            'pending_count' => $pendingCount,
            'approved_count' => $approvedCount,
            'approved_total' => $approvedTotal,
            'rejected_count' => $rejectedCount,
            'reviewed_count' => $approvedCount + $rejectedCount,
        ];
    }

    /**
     * @return Collection<int, PaymentCancellationRequest>
     */
    public function recentHistory(int $limit = 30): Collection
    {
        if (! PaymentCancellationRequest::schemaReady()) {
            return collect();
        }

        return PaymentCancellationRequest::query()
            ->whereIn('status', [
                PaymentCancellationRequestStatus::Approved,
                PaymentCancellationRequestStatus::Rejected,
            ])
            ->with([
                'payment.student',
                'payment.feeStructure.enrollment.course',
                'payment.feeMiscCharge',
                'payment.feeInstallment',
                'requestedBy',
                'reviewedBy',
            ])
            ->orderByDesc('reviewed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function isLatestActivePayment(Payment $payment): bool
    {
        $latestId = Payment::query()
            ->active()
            ->where('fee_structure_id', $payment->fee_structure_id)
            ->orderByDesc('id')
            ->value('id');

        return (int) $latestId === (int) $payment->id;
    }

    public function assertCanRequest(User $user): void
    {
        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return;
        }

        if (
            CrmAccess::can($user, CrmPermission::FeesCollect)
            || CrmAccess::can($user, CrmPermission::FeesAdjustStructure)
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'permission' => 'You are not allowed to request payment cancellations.',
        ]);
    }

    public function assertCanReview(User $user): void
    {
        if (! $user->hasRole(RoleName::SuperAdmin->value)) {
            throw ValidationException::withMessages([
                'permission' => 'Only Super Admin can approve or reject payment cancellation requests.',
            ]);
        }
    }

    protected function assertPaymentCancellable(Payment $payment): void
    {
        if (! $payment->isActive()) {
            throw ValidationException::withMessages([
                'payment' => 'This payment is already cancelled.',
            ]);
        }

        if (! $this->isLatestActivePayment($payment)) {
            throw ValidationException::withMessages([
                'payment' => 'Only the latest active payment on this fee account can be cancelled. Cancel newer payments first.',
            ]);
        }
    }
}
