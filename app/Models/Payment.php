<?php

namespace App\Models;

use App\Enums\PaymentMode;
use App\Enums\PaymentShortfallAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = [
        'fee_structure_id',
        'fee_installment_id',
        'student_id',
        'payment_date',
        'amount',
        'shortfall_allocation',
        'payment_mode',
        'voucher_number',
        'transaction_id',
        'utr_number',
        'proof_image_path',
        'receipt_number',
        'receipt_path',
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
            'shortfall_allocation' => 'array',
            'payment_mode' => PaymentMode::class,
            'corrected_at' => 'datetime',
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
            default => "{$amount} installment balance adjusted",
        };
    }
}
