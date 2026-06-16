<?php

namespace App\Services;

use App\Enums\NumberSequenceType;
use App\Enums\PaymentMode;
use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
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
    ) {}

    /**
     * @param  array{
     *     payment_date: string,
     *     amount: float|int|string,
     *     payment_mode: string,
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

        $feeStructure->loadMissing('enrollment');

        if ($feeStructure->enrollment?->student_id !== $student->id) {
            throw ValidationException::withMessages([
                'student' => 'Fee structure does not belong to this student.',
            ]);
        }

        $amount = round((float) $data['amount'], 2);
        $pending = round((float) $feeStructure->pending_amount, 2);

        if ($amount <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Payment amount must be greater than zero.',
            ]);
        }

        if ($amount > $pending) {
            throw ValidationException::withMessages([
                'amount' => "Amount cannot exceed pending fee of ₹".number_format($pending, 2).'.',
            ]);
        }

        $mode = PaymentMode::from($data['payment_mode']);
        $this->validateModeFields($mode, $data);

        return DB::transaction(function () use ($feeStructure, $student, $data, $proof, $staff, $amount, $mode): Payment {
            $receiptNumber = $this->numberGenerator->generate(NumberSequenceType::Receipt);

            $payment = Payment::query()->create([
                'fee_structure_id' => $feeStructure->id,
                'student_id' => $student->id,
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'payment_mode' => $mode,
                'voucher_number' => $data['voucher_number'] ?? null,
                'transaction_id' => $data['transaction_id'] ?? null,
                'utr_number' => $data['utr_number'] ?? null,
                'proof_image_path' => $this->storeProof($proof, $student->id, $receiptNumber),
                'receipt_number' => $receiptNumber,
                'added_by_user_id' => $staff->id,
            ]);

            $newPaid = round((float) $feeStructure->paid_amount + $amount, 2);
            $newPending = round(max(0, (float) $feeStructure->net_fee - $newPaid), 2);

            $feeStructure->update([
                'paid_amount' => $newPaid,
                'pending_amount' => $newPending,
            ]);

            $staff->loadMissing('staffProfile');

            $this->audit->log(
                action: 'Payment Added',
                auditable: $payment,
                newValues: [
                    'receipt_number' => $payment->receipt_number,
                    'amount' => $amount,
                    'payment_mode' => $mode->value,
                    'pending_amount' => $newPending,
                    'collected_by' => $staff->staffCollectorLabel(),
                    'collected_by_user_id' => $staff->id,
                ],
                user: $staff,
            );

            $payment = $this->receipts->generateForPayment($payment, $staff);

            if ($this->idCards->shouldGenerateForFirstPayment($payment)) {
                $this->idCards->generateForEnrollment($feeStructure->enrollment, $staff);
            }

            return $payment->fresh(['addedBy.staffProfile', 'feeStructure.enrollment.course', 'student']);
        });
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
