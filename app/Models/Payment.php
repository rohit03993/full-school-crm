<?php

namespace App\Models;

use App\Enums\PaymentMode;
use App\Enums\PaymentShortfallAction;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Payment extends Model
{
    protected $fillable = [
        'fee_structure_id',
        'fee_installment_id',
        'fee_misc_charge_id',
        'student_id',
        'payment_date',
        'amount',
        'tuition_amount',
        'shortfall_allocation',
        'allocation_snapshot',
        'payment_mode',
        'voucher_number',
        'transaction_id',
        'utr_number',
        'proof_image_path',
        'receipt_number',
        'receipt_path',
        'status',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancel_reason',
        'added_by_user_id',
        'correction_reason',
        'corrected_by_user_id',
        'corrected_at',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'amount' => 'decimal:2',
            'tuition_amount' => 'decimal:2',
            'shortfall_allocation' => 'array',
            'allocation_snapshot' => 'array',
            'payment_mode' => PaymentMode::class,
            'status' => PaymentStatus::class,
            'cancelled_at' => 'datetime',
            'corrected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment): void {
            if ($payment->status === null) {
                $payment->status = PaymentStatus::Active;
            }
        });
    }

    public function feeStructure(): BelongsTo
    {
        return $this->belongsTo(FeeStructure::class);
    }

    public function feeInstallment(): BelongsTo
    {
        return $this->belongsTo(FeeInstallment::class);
    }

    public function feeMiscCharge(): BelongsTo
    {
        return $this->belongsTo(FeeMiscCharge::class);
    }

    public function isMiscPayment(): bool
    {
        return $this->fee_misc_charge_id !== null;
    }

    public function isTuitionPayment(): bool
    {
        return $this->fee_misc_charge_id === null;
    }

    public function countsAsOnlineTuition(): bool
    {
        return $this->isTuitionPayment()
            && in_array($this->payment_mode, [PaymentMode::Online, PaymentMode::Upi], true);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by_user_id');
    }

    public function correctedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corrected_by_user_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function cancellationRequests(): HasMany
    {
        return $this->hasMany(PaymentCancellationRequest::class);
    }

    public function pendingCancellationRequest(): HasOne
    {
        return $this->hasOne(PaymentCancellationRequest::class)
            ->where('status', \App\Enums\PaymentCancellationRequestStatus::Pending);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', PaymentStatus::Active);
    }

    public function isActive(): bool
    {
        return ($this->status ?? PaymentStatus::Active) === PaymentStatus::Active;
    }

    public function isCancelled(): bool
    {
        return $this->status === PaymentStatus::Cancelled;
    }

    public function effectiveTuitionAmount(): float
    {
        return round((float) ($this->tuition_amount ?? $this->amount), 2);
    }

    public function hasReceiptPdf(): bool
    {
        return filled($this->receipt_path);
    }

    public function isProofImage(): bool
    {
        $extension = strtolower(pathinfo($this->proof_image_path, PATHINFO_EXTENSION));

        return in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true);
    }

    public function isProofPdf(): bool
    {
        return str_ends_with(strtolower($this->proof_image_path), '.pdf');
    }

    public function isProofPreviewable(): bool
    {
        return $this->isProofImage() || $this->isProofPdf();
    }

    public function proofPreviewUrl(): string
    {
        return route('admin.payments.proof.preview', $this);
    }

    public function proofDownloadUrl(): string
    {
        return route('admin.payments.proof.download', $this);
    }

    public function receiptPreviewUrl(): string
    {
        return route('admin.receipts.preview', $this);
    }

    public function receiptDownloadUrl(): string
    {
        return route('admin.receipts.download', $this);
    }

    public function portalReceiptDownloadUrl(): string
    {
        return route('portal.receipts.download', $this);
    }

    public function shortfallSummary(): ?string
    {
        $allocation = $this->shortfall_allocation;

        if (! is_array($allocation) || empty($allocation['amount'])) {
            return null;
        }

        $amount = '₹'.number_format((float) $allocation['amount'], 2);
        $target = $allocation['target_label'] ?? 'next installment';

        return match ($allocation['action'] ?? null) {
            PaymentShortfallAction::NewInstallment->value => "{$amount} balance scheduled as {$target}"
                .(filled($allocation['target_due_date'] ?? null) ? ' · due '.date('d M Y', strtotime((string) $allocation['target_due_date'])) : ''),
            PaymentShortfallAction::CarryForward->value => "{$amount} balance added to {$target}",
            PaymentShortfallAction::SurplusForward->value => $this->surplusForwardSummary($allocation),
            default => "{$amount} installment balance adjusted",
        };
    }

    /**
     * @param  array<string, mixed>  $allocation
     */
    protected function surplusForwardSummary(array $allocation): string
    {
        $amount = '₹'.number_format((float) ($allocation['amount'] ?? 0), 0);
        $source = $allocation['source_label'] ?? 'selected installment';
        $targets = collect($allocation['targets'] ?? [])
            ->map(function (array $target): string {
                $label = $target['label'] ?? 'installment';
                $applied = '₹'.number_format((float) ($target['amount'] ?? 0), 0);

                return "{$label} ({$applied})";
            })
            ->filter()
            ->implode(', ');

        if ($targets === '') {
            return "{$amount} extra applied to upcoming installments after clearing {$source}.";
        }

        return "{$amount} extra after clearing {$source} applied to: {$targets}.";
    }
}
