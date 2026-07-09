<?php

namespace App\Services;

use App\Enums\FeeMiscChargeAdjustmentRequestStatus;
use App\Enums\FeeMiscChargeAdjustmentType;
use App\Enums\FeeMiscChargeStatus;
use App\Enums\CrmPermission;
use App\Enums\RoleName;
use App\Models\FeeMiscCharge;
use App\Models\FeeMiscChargeAdjustmentRequest;
use App\Models\User;
use App\Support\CrmAccess;
use App\Support\CrmNavBadges;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class FeeMiscChargeAdjustmentService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    public function submitRequest(
        FeeMiscCharge $charge,
        User $staff,
        FeeMiscChargeAdjustmentType $type,
        ?float $discountAmount,
        string $reason,
    ): FeeMiscChargeAdjustmentRequest {
        if (! FeeMiscChargeAdjustmentRequest::schemaReady()) {
            throw ValidationException::withMessages([
                'charge' => 'Charge adjustment requests are not available yet. Run database migrations on the server (php artisan migrate).',
            ]);
        }

        $this->assertCanRequest($staff);
        $this->assertChargeAdjustable($charge);

        if ($this->hasPendingRequest($charge)) {
            throw ValidationException::withMessages([
                'charge' => 'A discount or waive-off request is already pending for this charge.',
            ]);
        }

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required for discount or waive-off requests.',
            ]);
        }

        $pending = $charge->pendingAmount();

        if ($pending <= 0) {
            throw ValidationException::withMessages([
                'charge' => 'This charge has no pending balance to adjust.',
            ]);
        }

        if ($type === FeeMiscChargeAdjustmentType::Discount) {
            $discountAmount = round((float) $discountAmount, 2);

            if ($discountAmount <= 0) {
                throw ValidationException::withMessages([
                    'discount_amount' => 'Enter a discount amount greater than zero.',
                ]);
            }

            if ($discountAmount > $pending + 0.01) {
                throw ValidationException::withMessages([
                    'discount_amount' => 'Discount cannot exceed the pending balance of ₹'.number_format($pending, 2).'.',
                ]);
            }
        } else {
            $discountAmount = null;
        }

        $request = FeeMiscChargeAdjustmentRequest::query()->create([
            'fee_misc_charge_id' => $charge->id,
            'requested_by_user_id' => $staff->id,
            'type' => $type,
            'discount_amount' => $discountAmount,
            'reason' => $reason,
            'status' => FeeMiscChargeAdjustmentRequestStatus::Pending,
        ]);

        $this->audit->log(
            action: 'Misc Charge Adjustment Requested',
            auditable: $request,
            newValues: [
                'charge_id' => $charge->id,
                'charge_label' => $charge->label,
                'type' => $type->value,
                'discount_amount' => $discountAmount,
                'pending_amount' => $pending,
                'reason' => $reason,
            ],
            user: $staff,
        );

        CrmNavBadges::flushMiscChargeAdjustmentBadgeCache();

        return $request->fresh(['charge', 'requestedBy']);
    }

    public function approve(FeeMiscChargeAdjustmentRequest $request, User $admin, ?string $reviewNotes = null): FeeMiscChargeAdjustmentRequest
    {
        $this->assertCanReview($admin);

        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be approved.',
            ]);
        }

        $charge = $request->charge()->firstOrFail();
        $this->assertChargeAdjustable($charge);

        return DB::transaction(function () use ($request, $admin, $reviewNotes, $charge): FeeMiscChargeAdjustmentRequest {
            $discountAmount = $request->type === FeeMiscChargeAdjustmentType::WaiveOff
                ? $charge->pendingAmount()
                : round((float) $request->discount_amount, 2);

            $updatedCharge = $this->applyAdjustment($charge, $discountAmount, $admin, $request->reason);

            $request->update([
                'status' => FeeMiscChargeAdjustmentRequestStatus::Approved,
                'applied_amount' => $discountAmount,
                'reviewed_by_user_id' => $admin->id,
                'reviewed_at' => now(),
                'review_notes' => filled($reviewNotes) ? trim($reviewNotes) : null,
            ]);

            $this->audit->log(
                action: 'Misc Charge Adjustment Approved',
                auditable: $request,
                newValues: [
                    'charge_id' => $updatedCharge->id,
                    'type' => $request->type->value,
                    'applied_amount' => $discountAmount,
                    'new_charge_amount' => (float) $updatedCharge->amount,
                    'new_status' => $updatedCharge->status->value,
                    'review_notes' => $request->review_notes,
                ],
                user: $admin,
            );

            CrmNavBadges::flushMiscChargeAdjustmentBadgeCache();

            return $request->fresh(['charge.feeStructure.enrollment.student', 'requestedBy', 'reviewedBy']);
        });
    }

    public function reject(FeeMiscChargeAdjustmentRequest $request, User $admin, ?string $reviewNotes = null): FeeMiscChargeAdjustmentRequest
    {
        $this->assertCanReview($admin);

        if (! $request->isPending()) {
            throw ValidationException::withMessages([
                'status' => 'Only pending requests can be rejected.',
            ]);
        }

        $reviewNotes = filled($reviewNotes) ? trim((string) $reviewNotes) : null;

        $request->update([
            'status' => FeeMiscChargeAdjustmentRequestStatus::Rejected,
            'reviewed_by_user_id' => $admin->id,
            'reviewed_at' => now(),
            'review_notes' => $reviewNotes,
        ]);

        $this->audit->log(
            action: 'Misc Charge Adjustment Rejected',
            auditable: $request,
            newValues: [
                'charge_id' => $request->fee_misc_charge_id,
                'review_notes' => $reviewNotes,
            ],
            user: $admin,
        );

        CrmNavBadges::flushMiscChargeAdjustmentBadgeCache();

        return $request->fresh(['charge.feeStructure.enrollment.student', 'requestedBy', 'reviewedBy']);
    }

    public function applyAdjustment(FeeMiscCharge $charge, float $discountAmount, User $staff, string $reason): FeeMiscCharge
    {
        $discountAmount = round($discountAmount, 2);
        $pending = $charge->pendingAmount();

        if ($discountAmount <= 0 || $discountAmount > $pending + 0.01) {
            throw ValidationException::withMessages([
                'discount_amount' => 'Invalid adjustment amount for this charge.',
            ]);
        }

        $paid = round((float) $charge->paid_amount, 2);

        if ($discountAmount >= $pending - 0.01 && $paid <= 0) {
            $charge->update([
                'status' => FeeMiscChargeStatus::Cancelled,
            ]);

            return $charge->fresh();
        }

        $newAmount = round((float) $charge->amount - $discountAmount, 2);

        if ($newAmount < $paid - 0.01) {
            throw ValidationException::withMessages([
                'discount_amount' => 'Adjustment would reduce the charge below what is already paid.',
            ]);
        }

        $status = $newAmount <= $paid + 0.01
            ? FeeMiscChargeStatus::Paid
            : ($paid > 0 ? FeeMiscChargeStatus::Partial : FeeMiscChargeStatus::Pending);

        $charge->update([
            'amount' => $newAmount,
            'status' => $status,
            'paid_at' => $status === FeeMiscChargeStatus::Paid ? now() : $charge->paid_at,
        ]);

        return $charge->fresh();
    }

    public function hasPendingRequest(FeeMiscCharge $charge): bool
    {
        return FeeMiscChargeAdjustmentRequest::query()
            ->where('fee_misc_charge_id', $charge->id)
            ->where('status', FeeMiscChargeAdjustmentRequestStatus::Pending)
            ->exists();
    }

    /**
     * @return Collection<int, FeeMiscChargeAdjustmentRequest>
     */
    public function pendingRequests(): Collection
    {
        if (! FeeMiscChargeAdjustmentRequest::schemaReady()) {
            return collect();
        }

        return FeeMiscChargeAdjustmentRequest::query()
            ->where('status', FeeMiscChargeAdjustmentRequestStatus::Pending)
            ->with([
                'charge.feeStructure.enrollment.student',
                'charge.feeStructure.enrollment.course',
                'requestedBy',
            ])
            ->orderBy('created_at')
            ->get();
    }

    public function pendingCount(): int
    {
        if (! FeeMiscChargeAdjustmentRequest::schemaReady()) {
            return 0;
        }

        return FeeMiscChargeAdjustmentRequest::query()
            ->where('status', FeeMiscChargeAdjustmentRequestStatus::Pending)
            ->count();
    }

    public function assertCanRequest(User $user): void
    {
        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return;
        }

        if (
            CrmAccess::can($user, CrmPermission::FeesWaivePenalty)
            || CrmAccess::can($user, CrmPermission::FeesAdjustStructure)
        ) {
            return;
        }

        throw ValidationException::withMessages([
            'permission' => 'You are not allowed to request charge discounts or waivers.',
        ]);
    }

    public function assertCanReview(User $user): void
    {
        if (! $user->hasRole(RoleName::SuperAdmin->value)) {
            throw ValidationException::withMessages([
                'permission' => 'Only Super Admin can approve or reject charge adjustment requests.',
            ]);
        }
    }

    protected function assertChargeAdjustable(FeeMiscCharge $charge): void
    {
        if (! $charge->isSeparateCharge()) {
            throw ValidationException::withMessages([
                'charge' => 'Only additional charges and penalties can be discounted or waived.',
            ]);
        }

        if ($charge->status === FeeMiscChargeStatus::Cancelled) {
            throw ValidationException::withMessages([
                'charge' => 'This charge is already cancelled.',
            ]);
        }

        if ($charge->pendingAmount() <= 0) {
            throw ValidationException::withMessages([
                'charge' => 'This charge has no pending balance to adjust.',
            ]);
        }
    }
}
