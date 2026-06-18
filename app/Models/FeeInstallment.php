<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeInstallment extends Model
{
    protected $fillable = [
        'fee_structure_id',
        'label',
        'amount',
        'due_date',
        'paid_amount',
        'pending_amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'due_date' => 'date',
            'paid_amount' => 'decimal:2',
            'pending_amount' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isOverdue(): bool
    {
        return $this->pending_amount > 0
            && $this->due_date !== null
            && $this->due_date->isPast();
    }

    public function statusLabel(): string
    {
        if ((float) $this->pending_amount <= 0) {
            return 'Paid';
        }

        if ($this->isOverdue()) {
            return 'Overdue';
        }

        if ((float) $this->paid_amount > 0) {
            return 'Partial';
        }

        return 'Pending';
    }
}
