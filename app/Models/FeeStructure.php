<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeStructure extends Model
{
    protected $fillable = [
        'enrollment_id',
        'course_fee',
        'discount_amount',
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

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(FeeStructureHistory::class);
    }
}
