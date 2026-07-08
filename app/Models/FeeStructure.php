<?php

namespace App\Models;

use App\Enums\FeeMiscChargeKind;
use App\Enums\FeeMiscChargeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeStructure extends Model
{
    protected $fillable = [
        'enrollment_id',
        'course_fee',
        'discount_amount',
        'discount_set_by_user_id',
        'net_fee',
        'paid_amount',
        'pending_amount',
        'planned_cash_amount',
        'planned_online_amount',
        'set_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'course_fee' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'net_fee' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'pending_amount' => 'decimal:2',
            'planned_cash_amount' => 'decimal:2',
            'planned_online_amount' => 'decimal:2',
        ];
    }

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function setBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'set_by_user_id');
    }

    public function discountSetBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'discount_set_by_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(FeeStructureHistory::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(FeeInstallment::class)
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function miscCharges(): HasMany
    {
        return $this->hasMany(FeeMiscCharge::class)->orderBy('sort_order');
    }

    public function discountEntries(): HasMany
    {
        return $this->hasMany(FeeDiscountEntry::class)->orderBy('created_at');
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(FeePenalty::class);
    }

    public function miscChargesTotal(): float
    {
        $charges = $this->relationLoaded('miscCharges')
            ? $this->miscCharges
            : $this->miscCharges()->get();

        return round((float) $charges
            ->filter(fn (FeeMiscCharge $charge): bool => $charge->isBundledInNetFee())
            ->sum('amount'), 2);
    }

    public function separateMiscCharges()
    {
        $charges = $this->relationLoaded('miscCharges')
            ? $this->miscCharges
            : $this->miscCharges()->get();

        return $charges->filter(fn (FeeMiscCharge $charge): bool => $charge->isSeparateCharge());
    }

    public function separateMiscChargesTotal(): float
    {
        return round((float) $this->separateMiscCharges()
            ->reject(fn (FeeMiscCharge $charge): bool => $charge->status === FeeMiscChargeStatus::Cancelled)
            ->sum('amount'), 2);
    }

    public function separateMiscChargesPaidTotal(): float
    {
        return round((float) $this->separateMiscCharges()
            ->reject(fn (FeeMiscCharge $charge): bool => $charge->status === FeeMiscChargeStatus::Cancelled)
            ->sum('paid_amount'), 2);
    }

    public function separateMiscChargesPendingTotal(): float
    {
        return round((float) $this->separateMiscCharges()
            ->sum(fn (FeeMiscCharge $charge): float => $charge->pendingAmount()), 2);
    }

    public function hasOnlineAllowancePlan(): bool
    {
        return $this->planned_cash_amount !== null
            && $this->planned_online_amount !== null;
    }

    public function tuitionBaseForAllowance(): float
    {
        return round((float) $this->net_fee, 2);
    }

    public function pendingPenaltiesTotal(): float
    {
        $charges = $this->relationLoaded('miscCharges')
            ? $this->miscCharges
            : $this->miscCharges()->get();

        return round((float) $charges
            ->filter(fn (FeeMiscCharge $charge): bool => $charge->kind === FeeMiscChargeKind::LateFeePenalty)
            ->sum(fn (FeeMiscCharge $charge): float => $charge->pendingAmount()), 2);
    }

    public function totalCollectiblePending(): float
    {
        return round(
            (float) $this->pending_amount
            + $this->separateMiscChargesPendingTotal(),
            2,
        );
    }

    /**
     * @param  Builder<FeeStructure>  $query
     * @return Builder<FeeStructure>
     */
    public function scopeForActiveEnrollments(Builder $query): Builder
    {
        return $query->whereHas('enrollment', fn (Builder $enrollmentQuery): Builder => $enrollmentQuery->where('is_active', true));
    }
}
