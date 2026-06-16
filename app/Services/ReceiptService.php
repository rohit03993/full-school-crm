<?php

namespace App\Services;

use App\Enums\PaymentMode;
use App\Models\Payment;
use App\Models\User;
use App\Support\IndianAmountInWords;
use App\Support\InstituteSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class ReceiptService
{
    public const DISK = 'local';

    public function __construct(
        protected AuditService $audit,
        protected StorageCleanupService $storage,
    ) {}

    public function generateForPayment(Payment $payment, ?User $staff = null, bool $regenerate = false): Payment
    {
        if ($payment->hasReceiptPdf() && ! $regenerate) {
            return $payment;
        }

        $payment->loadMissing([
            'student',
            'feeStructure.enrollment.course',
            'addedBy.staffProfile',
        ]);

        $enrollment = $payment->feeStructure?->enrollment;

        if (! $enrollment) {
            throw new \RuntimeException('Payment is not linked to an enrollment.');
        }

        $relativePath = "receipts/{$enrollment->enrollment_number}/{$payment->receipt_number}.pdf";
        $previousPath = $payment->receipt_path;

        $this->storage->replaceStoredFile($previousPath, $relativePath);

        $institute = InstituteSettings::forDocuments();

        $pdf = Pdf::loadView('pdf.payment-receipt', [
            'payment' => $payment,
            'student' => $payment->student,
            'enrollment' => $enrollment,
            'course' => $enrollment->course,
            'collector' => $payment->addedBy,
            'amountInWords' => IndianAmountInWords::format($payment->amount),
            'modeReference' => $this->modeReference($payment),
            'institute' => $institute,
            'footer' => $institute['footer'],
        ])->setPaper('a4');

        Storage::disk(self::DISK)->put($relativePath, $pdf->output());

        $payment->update(['receipt_path' => $relativePath]);

        $this->audit->log(
            action: $regenerate ? 'Receipt Regenerated' : 'Receipt Generated',
            auditable: $payment,
            newValues: [
                'receipt_number' => $payment->receipt_number,
                'receipt_path' => $relativePath,
            ],
            user: $staff ?? $payment->addedBy,
        );

        return $payment->fresh(['addedBy.staffProfile', 'feeStructure.enrollment.course', 'student']);
    }

    protected function modeReference(Payment $payment): ?string
    {
        return match ($payment->payment_mode) {
            PaymentMode::Cash => $payment->voucher_number ? "Voucher: {$payment->voucher_number}" : null,
            PaymentMode::Online => $payment->transaction_id ? "Txn ID: {$payment->transaction_id}" : null,
            PaymentMode::Upi => $payment->utr_number ? "UTR: {$payment->utr_number}" : null,
        };
    }
}
