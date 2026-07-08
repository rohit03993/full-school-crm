<?php

namespace App\Models;

use App\Enums\FeeMiscChargeKind;
use App\Enums\FeeMiscChargeStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeMiscCharge extends Model
{
    protected $fillable = [
        'fee_structure_id',
        'fee_installment_id',
        'label',
        'amount',
        'paid_amount',
        'kind',
        'status',
        'due_date',
        'added_by_user_id',
        'paid_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'kind' => FeeMiscChargeKind::class,
            'status' => FeeMiscChargeStatus::class,
            'due_date' => 'date',
            'paid_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function feeInstallment(): BelongsTo
    {
        return $this->belongsTo(FeeInstallment::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isBundledInNetFee(): bool
    {
        return $this->kind === FeeMiscChargeKind::Bundled;
    }

    public function isSeparateCharge(): bool
    {
        return in_array($this->kind, [
            FeeMiscChargeKind::Separate,
            FeeMiscChargeKind::GstPenalty,
            FeeMiscChargeKind::LateFeePenalty,
        ], true);
    }

    public function isLateFeePenalty(): bool
    {
        return $this->kind === FeeMiscChargeKind::LateFeePenalty;
    }

    public function isGstPenalty(): bool
    {
        return $this->kind === FeeMiscChargeKind::GstPenalty;
    }

    public function pendingAmount(): float
    {
        if (! $this->isSeparateCharge() || $this->status === FeeMiscChargeStatus::Cancelled) {
            return 0.0;
        }

        if ($this->status === FeeMiscChargeStatus::Paid) {
            return 0.0;
        }

        return round(max(0, (float) $this->amount - (float) $this->paid_amount), 2);
    }

    public function isPayableSeparately(): bool
    {
        return $this->isSeparateCharge()
            && in_array($this->status, [FeeMiscChargeStatus::Pending, FeeMiscChargeStatus::Partial], true)
            && $this->pendingAmount() > 0;
    }

    /**
     * @param  Builder<FeeMiscCharge>  $query
     * @return Builder<FeeMiscCharge>
     */
    public function scopeSeparatePayable(Builder $query): Builder
    {
        return $query
            ->whereIn('kind', [
                FeeMiscChargeKind::Separate,
                FeeMiscChargeKind::GstPenalty,
                FeeMiscChargeKind::LateFeePenalty,
            ])
            ->where('status', '!=', FeeMiscChargeStatus::Cancelled);
    }
}
