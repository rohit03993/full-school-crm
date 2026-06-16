<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FeeStructureHistory extends Model
{
    protected $table = 'fee_structure_history';

    protected $fillable = [
        'fee_structure_id',
        'old_course_fee',
        'new_course_fee',
        'old_discount',
        'new_discount',
        'old_net_fee',
        'new_net_fee',
        'changed_by_user_id',
        'reason',
        'changed_at',
    ];

    protected function casts(): array
    {
        return [
            'old_course_fee' => 'decimal:2',
            'new_course_fee' => 'decimal:2',
            'old_discount' => 'decimal:2',
            'new_discount' => 'decimal:2',
            'old_net_fee' => 'decimal:2',
            'new_net_fee' => 'decimal:2',
            'changed_at' => 'datetime',
        ];
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
