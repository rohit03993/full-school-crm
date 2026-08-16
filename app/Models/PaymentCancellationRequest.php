<?php

namespace App\Models;

use App\Enums\PaymentCancellationRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class PaymentCancellationRequest extends Model
{
    public static function schemaReady(): bool
    {
        return Schema::hasTable('payment_cancellation_requests');
    }

    protected $fillable = [
        'payment_id',
        'requested_by_user_id',
        'reviewed_by_user_id',
        'reason',
        'status',
        'review_notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentCancellationRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
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
        return $this->status === PaymentCancellationRequestStatus::Pending;
    }
}
