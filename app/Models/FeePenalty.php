<?php

namespace App\Models;

use App\Enums\FeePenaltyStatus;
use App\Enums\FeePenaltyType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeePenalty extends Model
{
    protected $fillable = [
        'student_id',
        'fee_structure_id',
        'fee_installment_id',
        'penalty_type',
        'penalty_date',
        'base_amount',
        'penalty_amount',
        'days_late',
        'description',
        'status',
        'waived_by_user_id',
        'waived_reason',
    ];

    protected function casts(): array
    {
        return [
            'penalty_type' => FeePenaltyType::class,
            'penalty_date' => 'date',
            'base_amount' => 'decimal:2',
            'penalty_amount' => 'decimal:2',
            'days_late' => 'integer',
            'status' => FeePenaltyStatus::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function feeInstallment(): BelongsTo
    {
        return $this->belongsTo(FeeInstallment::class);
    }

    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === FeePenaltyStatus::Pending;
    }
}
