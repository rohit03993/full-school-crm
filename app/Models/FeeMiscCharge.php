<?php

namespace App\Models;

use App\Enums\FeeMiscChargeKind;
use App\Enums\FeeMiscChargeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeMiscCharge extends Model
{
    protected $fillable = [
        'fee_structure_id',
        'label',
        'amount',
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

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isPayableSeparately(): bool
    {
        return in_array($this->kind, [FeeMiscChargeKind::Separate, FeeMiscChargeKind::GstPenalty], true)
            && $this->status === FeeMiscChargeStatus::Pending;
    }

    public function isBundledInNetFee(): bool
    {
        return $this->kind === FeeMiscChargeKind::Bundled;
    }
}
