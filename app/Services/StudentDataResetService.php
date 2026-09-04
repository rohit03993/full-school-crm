<?php

namespace App\Services;

use App\Enums\NumberSequenceType;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\HomeworkAssignment;
use App\Models\MetaWhatsAppMessage;
use App\Models\Payment;
use App\Models\StudentCertificate;
use App\Models\StudentMarksheet;
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
            $counts['push_subscriptions'] = $this->clearStudentPushSubscriptions();
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

        if (Schema::hasTable('meta_whatsapp_messages')) {
            MetaWhatsAppMessage::query()
                ->whereNotNull('media_path')
                ->select(['media_path'])
                ->cursor()
                ->each(function (MetaWhatsAppMessage $message): void {
                    $this->storage->deleteStoredFile($message->media_path);
                });
        }

        if (Schema::hasTable('student_marksheets')) {
            StudentMarksheet::query()
                ->whereNotNull('pdf_path')
                ->select(['pdf_path'])
                ->cursor()
                ->each(function (StudentMarksheet $marksheet): void {
                    $this->storage->deleteStoredFile($marksheet->pdf_path);
                });
        }

        if (Schema::hasTable('homework_assignments')) {
            HomeworkAssignment::query()
                ->whereNotNull('file_path')
                ->select(['file_path'])
                ->cursor()
                ->each(function (HomeworkAssignment $homework): void {
                    $this->storage->deleteStoredFile($homework->file_path);
                });
        }

        if (Schema::hasTable('student_certificates')) {
            StudentCertificate::query()
                ->whereNotNull('pdf_path')
                ->select(['pdf_path'])
                ->cursor()
                ->each(function (StudentCertificate $certificate): void {
                    $this->storage->deleteStoredFile($certificate->pdf_path);
                });
        }
    }

    /**
     * @return list<string>
     */
    protected function tablesToClear(): array
    {
        return [
            'accounting_journal_lines',
            'accounting_journal_entries',
            'fee_reminder_logs',
            'meta_whatsapp_messages',
            'attendance_punch_whatsapp_logs',
            'whatsapp_campaign_recipients',
            'whatsapp_campaigns',
            'whatsapp_live_campaigns',
            'student_marksheets',
            'result_declarations',
            'exam_window_subjects',
            'exam_windows',
            'homework_checks',
            'homework_views',
            'homework_assignments',
            'activity_attendances',
            'activity_sessions',
            'attendance_manual_punches',
            'attendances',
            'batch_students',
            'student_case_assignments',
            'student_calls',
            'student_cases',
            'student_certificates',
            'visit_meeting_assignments',
            'face_verification_requests',
            'documents',
            'payment_cancellation_requests',
            'payments',
            'fee_misc_charge_adjustment_requests',
            'fee_penalties',
            'fee_discount_entries',
            'fee_misc_charges',
            'fee_installments',
            'fee_structure_history',
            'fee_structures',
            'admission_misc_fees',
            'admission_installment_plans',
            'enrollments',
            'admissions',
            'visits',
            'enquiries',
            'student_import_batches',
            'audit_logs',
            'notifications',
            'students',
        ];
    }

    protected function resetStudentNumberSequences(): int
    {
        $types = array_map(
            fn (NumberSequenceType $type): string => $type->value,
            NumberSequenceType::cases(),
        );

        $deleted = DB::table('number_sequences')
            ->whereIn('type', $types)
            ->delete();

        if (Schema::hasTable('marksheet_serial_sequences')) {
            DB::table('marksheet_serial_sequences')->update(['last_value' => 0]);
        }

        return $deleted;
    }

    protected function clearStudentPushSubscriptions(): int
    {
        if (! Schema::hasTable('push_subscriptions') || ! Schema::hasColumn('push_subscriptions', 'student_id')) {
            return 0;
        }

        return DB::table('push_subscriptions')->whereNotNull('student_id')->delete();
    }

    /**
     * Tables and settings left intact by {@see reset()}.
     *
     * @return list<string>
     */
    public static function preservedSummary(): array
    {
        return [
            'Staff logins (users, roles, staff profiles)',
            'Institute settings (name, logo, WhatsApp/Meta connection tokens)',
            'Meta WhatsApp templates (synced from Meta)',
            'Academic structure (sessions, programmes/courses, subjects, sections/batches)',
            'Subject teachers & batch staff assignments',
            'Activity types (Exam, etc.)',
            'Accounting chart of accounts (ledger account list)',
            'Course fee templates & installment templates',
            'Website gallery & custom field definitions',
            'License / feature settings',
        ];
    }

    /**
     * Data categories removed by {@see reset()}.
     *
     * @return list<string>
     */
    public static function clearedSummary(): array
    {
        return [
            'All students and import history',
            'Leads, enquiries, campus visits, assigned meetings',
            'Student cases (My work / All cases) and case assignments',
            'Certificates issued to students',
            'Admissions, enrollments, documents, ID cards',
            'Fees, installments, discounts, penalties, payments, receipts, cancel/adjust requests',
            'Fee reminder logs & accounting journal entries',
            'Batch attendance & biometric/manual punch logs',
            'Exam windows, test sessions, marks, result declarations, marksheets',
            'Homework assignments, checks, and view history',
            'Call queue / call logs tied to students',
            'WhatsApp inbox messages, bulk campaigns, and live campaigns',
            'WhatsApp punch notification logs',
            'Portal push subscriptions (staff push kept)',
            'Audit log history & in-app notifications',
            'Student roll/receipt/case number sequences (reset to start)',
        ];
    }
}
