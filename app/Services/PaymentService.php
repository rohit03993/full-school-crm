<?php

namespace App\Services;

use App\Enums\NumberSequenceType;
use App\Enums\PaymentMode;
use App\Enums\PaymentShortfallAction;
use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Support\CrmCacheInvalidator;
use App\Support\FeePaymentPolicy;
use App\Support\PaymentShortfallHelper;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public const DISK = 'local';

    public function __construct(
        protected NumberGeneratorService $numberGenerator,
        protected AuditService $audit,
        protected ReceiptService $receipts,
        protected IdCardService $idCards,
        protected FeeInstallmentService $installments,
        protected PenaltyCalculationService $penalties,
    ) {}

    /**
     * @param  array{
     *     payment_date: string,
     *     amount: float|int|string,
     *     payment_mode: string,
     *     fee_installment_id?: int|null,
     *     shortfall_action?: string|null,
     *     shortfall_due_date?: string|null,
     *     shortfall_label?: string|null,
     *     voucher_number?: string|null,
     *     transaction_id?: string|null,
     *     utr_number?: string|null,
     * }  $data
     */
    public function add(
        FeeStructure $feeStructure,
        Student $student,
        array $data,
        UploadedFile|string $proof,
        User $staff,
    ): Payment {
        Gate::forUser($staff)->authorize('create', Payment::class);

        $feeStructure->loadMissing('enrollment', 'installments');

        if ($feeStructure->enrollment?->student_id !== $student->id) {
            throw ValidationException::withMessages([
                'student' => 'Fee structure does not belong to this student.',
            ]);
        }

        $amount = round((float) $data['amount'], 2);
        $collectible = round((float) $feeStructure->totalCollectiblePending(), 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        if ($amount > $collectible) {
            throw ValidationException::withMessages([
                'amount' => "Amount cannot exceed outstanding balance of ₹".number_format($collectible, 2).'.',
            ]);
        }

        $pending = round((float) $feeStructure->pending_amount, 2);
        $feePaymentAmount = min($amount, $pending);

        $installment = $this->resolveInstallment($feeStructure, $data['fee_installment_id'] ?? null);
        $flexible = FeePaymentPolicy::usesFlexibleAllocation();

        if (! $flexible && $installment && $feePaymentAmount > round((float) $installment->pending_amount, 2)) {
            throw ValidationException::withMessages([
                'amount' => "Amount cannot exceed pending for {$installment->label} (₹"
                    .number_format((float) $installment->pending_amount, 2).').',
            ]);
        }

        $mode = PaymentMode::from($data['payment_mode']);
        $this->validateModeFields($mode, $data);

        return DB::transaction(function () use ($feeStructure, $student, $data, $proof, $staff, $amount, $mode, $flexible): Payment {
            $locked = FeeStructure::query()
                ->whereKey($feeStructure->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->loadMissing('enrollment', 'installments', 'penalties');

            $pending = round((float) $locked->pending_amount, 2);
            $collectible = round((float) $locked->totalCollectiblePending(), 2);

            if ($amount > $collectible) {
                throw ValidationException::withMessages([
                    'amount' => "Amount cannot exceed outstanding balance of ₹".number_format($collectible, 2).'.',
                ]);
            }

            $feePaymentAmount = min($amount, $pending);
            $penaltyPaymentAmount = round($amount - $feePaymentAmount, 2);

            $installment = $this->resolveInstallment($locked, $data['fee_installment_id'] ?? null);

            if (! $flexible && $installment && $feePaymentAmount > round((float) $installment->pending_amount, 2)) {
                throw ValidationException::withMessages([
                    'amount' => "Amount cannot exceed pending for {$installment->label} (₹"
                        .number_format((float) $installment->pending_amount, 2).').',
                ]);
            }

            $shortfallHandling = $this->resolveShortfallHandling($installment, $feePaymentAmount, $data, $flexible);

            if ($feePaymentAmount > 0 && ($installment || $locked->installments->isNotEmpty())) {
                $installmentPendingTotal = round((float) $locked->installments->sum(
                    fn (\App\Models\FeeInstallment $row): float => (float) $row->pending_amount,
                ), 2);

                if ($installmentPendingTotal > 0 && abs($installmentPendingTotal - $pending) > 0.02) {
                    throw ValidationException::withMessages([
                        'amount' => 'Installment totals do not match the fee balance. Contact an administrator.',
                    ]);
                }
            }

            $receiptNumber = $this->numberGenerator->generate(NumberSequenceType::Receipt);

            $payment = Payment::query()->create([
                'fee_structure_id' => $locked->id,
                'fee_installment_id' => $installment?->id,
                'student_id' => $student->id,
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'shortfall_allocation' => null,
                'payment_mode' => $mode,
                'voucher_number' => $data['voucher_number'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'utr_number' => $data['utr_number'] ?? null,
                'proof_image_path' => $this->storeProof($proof, $student->id, $receiptNumber),
                'receipt_number' => $receiptNumber,
                'added_by_user_id' => $staff->id,
            ]);

            $newPaid = round((float) $locked->paid_amount + $feePaymentAmount, 2);
            $newPending = round(max(0, (float) $locked->net_fee - $newPaid), 2);

            $locked->update([
                'paid_amount' => $newPaid,
                'pending_amount' => $newPending,
            ]);

            if ($feePaymentAmount > 0 && ($installment || $locked->installments->isNotEmpty())) {
                if ($flexible) {
                    $allocationResult = $this->installments->allocatePayment(
                        $locked,
                        $installment,
                        $feePaymentAmount,
                        $shortfallHandling,
                    );

                    if ($allocationResult['shortfall_allocation']) {
                        $payment->update([
                            'shortfall_allocation' => $allocationResult['shortfall_allocation'],
                        ]);
                    }
                } elseif ($installment) {
                    $this->installments->applyPayment($installment, $feePaymentAmount);
                }
            }

            if ($penaltyPaymentAmount > 0) {
                $this->penalties->applyPendingPayments($locked, $penaltyPaymentAmount);
            }

            $staff->loadMissing('staffProfile');

            $this->audit->log(
                action: 'Payment Added',
                auditable: $payment,
                newValues: [
                    'receipt_number' => $payment->receipt_number,
                    'amount' => $amount,
                    'payment_mode' => $mode->value,
                    'installment' => $installment?->label,
                    'shortfall_allocation' => $payment->shortfall_allocation,
                    'pending_amount' => $newPending,
                    'collected_by' => $staff->staffCollectorLabel(),
                    'collected_by_user_id' => $staff->id,
                ],
                user: $staff,
            );

            $payment = $this->receipts->generateForPayment($payment, $staff);

            if ($this->idCards->shouldGenerateForFirstPayment($payment)) {
                $this->idCards->generateForEnrollment($locked->enrollment, $staff);
            }

            CrmCacheInvalidator::afterPayment();

            return $payment->fresh(['addedBy.staffProfile', 'feeStructure.enrollment.course', 'feeInstallment', 'student']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{action: string, due_date?: string|null, label?: string|null}|null
     */
    protected function resolveShortfallHandling(
        ?\App\Models\FeeInstallment $installment,
        float $amount,
        array $data,
        bool $flexible,
    ): ?array {
        if (! $flexible || ! $installment) {
            return null;
        }

        $shortfall = PaymentShortfallHelper::shortfallAmount($amount, $installment);

        if ($shortfall <= 0) {
            return null;
        }

        $action = PaymentShortfallAction::tryFrom((string) ($data['shortfall_action'] ?? ''));

        if (! $action) {
            throw ValidationException::withMessages([
                'shortfall_action' => 'Choose how to handle the remaining ₹'.number_format($shortfall, 2).'.',
            ]);
        }

        if ($action === PaymentShortfallAction::CarryForward
            && ! PaymentShortfallHelper::hasNextPayableInstallment($installment)) {
            throw ValidationException::withMessages([
                'shortfall_action' => 'No next installment exists. Create a new installment for the balance instead.',
            ]);
        }

        if ($action === PaymentShortfallAction::NewInstallment && ! filled($data['shortfall_due_date'] ?? null)) {
            throw ValidationException::withMessages([
                'shortfall_due_date' => 'Due date is required for the new installment.',
            ]);
        }

        return [
            'action' => $action->value,
            'due_date' => $data['shortfall_due_date'] ?? null,
            'label' => filled($data['shortfall_label'] ?? null)
                ? trim((string) $data['shortfall_label'])
                : PaymentShortfallHelper::suggestNewInstallmentLabel($installment->fee_structure_id),
        ];
    }

    protected function resolveInstallment(FeeStructure $feeStructure, mixed $installmentId): ?\App\Models\FeeInstallment
    {
        if ($feeStructure->installments->isEmpty()) {
            return null;
        }

        if (filled($installmentId)) {
            $installment = $feeStructure->installments->firstWhere('id', (int) $installmentId);

            if (! $installment || (float) $installment->pending_amount <= 0) {
                throw ValidationException::withMessages([
                    'fee_installment_id' => 'Selected installment is not payable.',
                ]);
            }

            return $installment;
        }

        return $this->installments->firstPayableInstallment($feeStructure);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function validateModeFields(PaymentMode $mode, array $data): void
    {
        match ($mode) {
            PaymentMode::Cash => $this->requireField($data, 'voucher_number', 'Voucher number is required for cash payments.'),
            PaymentMode::Online => $this->requireField($data, 'transaction_id', 'Transaction ID is required for online payments.'),
            PaymentMode::Upi => $this->requireField($data, 'utr_number', 'UTR number is required for UPI payments.'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function requireField(array $data, string $key, string $message): void
    {
        if (! filled($data[$key] ?? null)) {
            throw ValidationException::withMessages([$key => $message]);
        }
    }

    protected function storeProof(UploadedFile|string $proof, int $studentId, string $receiptNumber): string
    {
        $directory = "payments/{$studentId}/{$receiptNumber}";

        if ($proof instanceof UploadedFile) {
            $extension = $proof->getClientOriginalExtension() ?: $proof->extension() ?: 'jpg';
            $filename = 'proof.'.strtolower($extension);

            Storage::disk(self::DISK)->putFileAs($directory, $proof, $filename);

            return "{$directory}/{$filename}";
        }

        if (is_string($proof) && Storage::disk(self::DISK)->exists($proof)) {
            $extension = pathinfo($proof, PATHINFO_EXTENSION) ?: 'jpg';
            $filename = 'proof.'.strtolower($extension);
            $destination = "{$directory}/{$filename}";

            Storage::disk(self::DISK)->move($proof, $destination);

            return $destination;
        }

        throw ValidationException::withMessages([
            'proof_image' => 'Payment proof file is required.',
        ]);
    }

    public function proofDownloadPath(Payment $payment): ?string
    {
        if (! Storage::disk(self::DISK)->exists($payment->proof_image_path)) {
            return null;
        }

        return Storage::disk(self::DISK)->path($payment->proof_image_path);
    }
}
