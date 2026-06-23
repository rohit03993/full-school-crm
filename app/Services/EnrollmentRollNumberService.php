<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class EnrollmentRollNumberService
{
    public function __construct(
        protected AuditService $audit,
        protected IdCardService $idCards,
        protected ReceiptService $receipts,
        protected StorageCleanupService $storage,
    ) {}

    public function update(Enrollment $enrollment, string $rollNumber, ?User $staff = null): Enrollment
    {
        $normalized = strtoupper(trim($rollNumber));

        $this->validate($enrollment, $normalized);

        if ($normalized === $enrollment->enrollment_number) {
            return $enrollment;
        }

        return DB::transaction(function () use ($enrollment, $normalized, $staff): Enrollment {
            $oldNumber = $enrollment->enrollment_number;
            $hadIdCard = $enrollment->hasIdCard();
            $oldIdCardPath = $enrollment->id_card_path;

            $enrollment->update(['enrollment_number' => $normalized]);
            $enrollment = $enrollment->fresh(['feeStructure.payments']);

            $this->migrateReceiptFiles($enrollment, $staff);

            if ($hadIdCard) {
                $this->idCards->generateForEnrollment($enrollment, $staff, regenerate: true);
            } elseif (filled($oldIdCardPath)) {
                $this->storage->deleteStoredFile($oldIdCardPath);
            }

            $this->audit->log(
                action: 'Roll Number Updated',
                auditable: $enrollment,
                oldValues: ['enrollment_number' => $oldNumber],
                newValues: ['enrollment_number' => $normalized],
                user: $staff,
            );

            return $enrollment->fresh(['student', 'course', 'feeStructure']);
        });
    }

    protected function validate(Enrollment $enrollment, string $rollNumber): void
    {
        $validator = Validator::make(
            ['enrollment_number' => $rollNumber],
            [
                'enrollment_number' => [
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('enrollments', 'enrollment_number')->ignore($enrollment->id),
                ],
            ],
            [
                'enrollment_number.required' => 'Roll number is required.',
                'enrollment_number.unique' => 'This roll number is already assigned to another student.',
            ],
        );

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }
    }

    protected function migrateReceiptFiles(Enrollment $enrollment, ?User $staff): void
    {
        $payments = $enrollment->feeStructure?->payments ?? collect();

        /** @var Payment $payment */
        foreach ($payments as $payment) {
            if (! $payment->hasReceiptPdf()) {
                continue;
            }

            $this->receipts->generateForPayment(
                $payment->fresh(['feeStructure.enrollment.course', 'student']),
                $staff,
                regenerate: true,
            );
        }
    }
}
