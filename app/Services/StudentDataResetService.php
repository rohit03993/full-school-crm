<?php

namespace App\Services;

use App\Enums\NumberSequenceType;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class StudentDataResetService
{
    public function __construct(
        protected StorageCleanupService $storage,
    ) {}

    /**
     * @return array<string, int>
     */
    public function reset(): array
    {
        $counts = [];

        $this->deleteStoredFiles();

        // MySQL TRUNCATE implicitly commits — do not wrap in DB::transaction().
        Schema::disableForeignKeyConstraints();

        try {
            foreach ($this->tablesToClear() as $table) {
                if (! Schema::hasTable($table)) {
                    continue;
                }

                $counts[$table] = (int) DB::table($table)->count();
                DB::table($table)->truncate();
            }

            $counts['number_sequences'] = $this->resetStudentNumberSequences();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        return $counts;
    }

    protected function deleteStoredFiles(): void
    {
        Payment::query()
            ->select(['proof_image_path', 'receipt_path'])
            ->cursor()
            ->each(function (Payment $payment): void {
                $this->storage->deleteStoredFile($payment->proof_image_path);
                $this->storage->deleteStoredFile($payment->receipt_path);
            });

        Document::query()
            ->select(['file_path'])
            ->cursor()
            ->each(function (Document $document): void {
                $this->storage->deleteStoredFile($document->file_path);
            });

        Enrollment::query()
            ->whereNotNull('id_card_path')
            ->select(['id_card_path'])
            ->cursor()
            ->each(function (Enrollment $enrollment): void {
                $this->storage->deleteStoredFile($enrollment->id_card_path);
            });
    }

    /**
     * @return list<string>
     */
    protected function tablesToClear(): array
    {
        return [
            'whatsapp_campaign_recipients',
            'whatsapp_campaigns',
            'payments',
            'fee_penalties',
            'fee_discount_entries',
            'fee_misc_charges',
            'fee_installments',
            'fee_structure_history',
            'fee_structures',
            'admission_misc_fees',
            'admission_installment_plans',
            'activity_attendances',
            'activity_sessions',
            'attendances',
            'batch_students',
            'student_calls',
            'documents',
            'enrollments',
            'admissions',
            'visits',
            'enquiries',
            'student_import_batches',
            'students',
        ];
    }

    protected function resetStudentNumberSequences(): int
    {
        $types = array_map(
            fn (NumberSequenceType $type): string => $type->value,
            NumberSequenceType::cases(),
        );

        return DB::table('number_sequences')
            ->whereIn('type', $types)
            ->delete();
    }
}
