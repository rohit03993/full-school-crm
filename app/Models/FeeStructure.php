<?php

namespace App\Models;

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
        if ($this->relationLoaded('miscCharges')) {
            return round((float) $this->miscCharges->sum('amount'), 2);
        }

        return round((float) $this->miscCharges()->sum('amount'), 2);
    }

    public function pendingPenaltiesTotal(): float
    {
        return round((float) $this->penalties()
            ->where('status', \App\Enums\FeePenaltyStatus::Pending)
            ->sum('penalty_amount'), 2);
    }

    public function totalCollectiblePending(): float
    {
        return round((float) $this->pending_amount + $this->pendingPenaltiesTotal(), 2);
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
