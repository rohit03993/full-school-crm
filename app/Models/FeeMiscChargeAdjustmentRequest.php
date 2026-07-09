<?php

namespace App\Models;

use App\Enums\FeeMiscChargeAdjustmentRequestStatus;
use App\Enums\FeeMiscChargeAdjustmentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class FeeMiscChargeAdjustmentRequest extends Model
{
    public static function schemaReady(): bool
    {
        return Schema::hasTable('fee_misc_charge_adjustment_requests');
    }
    protected $fillable = [
        'fee_misc_charge_id',
        'requested_by_user_id',
        'reviewed_by_user_id',
        'type',
        'discount_amount',
        'reason',
        'status',
        'review_notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => FeeMiscChargeAdjustmentType::class,
            'status' => FeeMiscChargeAdjustmentRequestStatus::class,
            'discount_amount' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    public function charge(): BelongsTo
    {
        return $this->belongsTo(FeeMiscCharge::class, 'fee_misc_charge_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === FeeMiscChargeAdjustmentRequestStatus::Pending;
    }

    public function resolvedDiscountAmount(): float
    {
        if ($this->type === FeeMiscChargeAdjustmentType::WaiveOff) {
            return round((float) ($this->charge?->pendingAmount() ?? 0), 2);
        }

        return round((float) ($this->discount_amount ?? 0), 2);
    }
}
