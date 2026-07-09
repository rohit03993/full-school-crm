<?php

namespace Tests\Unit;

use App\Enums\AccountingReferenceType;
use App\Enums\AdmissionStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Enums\FeePenaltyStatus;
use App\Enums\FeePenaltyType;
use App\Enums\PaymentMode;
use App\Enums\StudentStatus;
use App\Models\AccountingJournalEntry;
use App\Models\AccountingJournalLine;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\FeePenalty;
use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\AccountingLedgerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountingLedgerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_posts_balanced_payment_entry(): void
    {
        $staff = User::factory()->create();
        $student = $this->createStudent();
        $feeStructure = $this->createFeeStructure($student);

        $payment = Payment::query()->create([
            'fee_structure_id' => $feeStructure->id,
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'amount' => 15000,
            'payment_mode' => PaymentMode::Upi,
            'receipt_number' => 'RCP-2026-0001',
            'proof_image_path' => 'proofs/test.jpg',
            'added_by_user_id' => $staff->id,
        ]);

        $entry = app(AccountingLedgerService::class)->postPayment($payment, 12000, 3000, $staff);

        $this->assertNotNull($entry);
        $this->assertSame(AccountingReferenceType::Payment, $entry->reference_type);
        $this->assertSame($payment->id, $entry->reference_id);

        $lines = AccountingJournalLine::query()->where('journal_entry_id', $entry->id)->get();
        $this->assertCount(3, $lines);
        $this->assertSame(15000.0, round((float) $lines->sum('debit'), 2));
        $this->assertSame(15000.0, round((float) $lines->sum('credit'), 2));
    }

    public function test_skips_duplicate_payment_posting(): void
    {
        $staff = User::factory()->create();
        $student = $this->createStudent();
        $feeStructure = $this->createFeeStructure($student);

        $payment = Payment::query()->create([
            'fee_structure_id' => $feeStructure->id,
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'amount' => 5000,
            'payment_mode' => PaymentMode::Cash,
            'receipt_number' => 'RCP-2026-0002',
            'proof_image_path' => 'proofs/test.jpg',
            'added_by_user_id' => $staff->id,
        ]);

        $ledger = app(AccountingLedgerService::class);
        $ledger->postPayment($payment, 5000, 0, $staff);
        $second = $ledger->postPayment($payment, 5000, 0, $staff);

        $this->assertNull($second);
        $this->assertSame(1, AccountingJournalEntry::query()->count());
    }

    public function test_posts_penalty_accrual_entry(): void
    {
        $student = $this->createStudent();
        $feeStructure = $this->createFeeStructure($student);

        $penalty = FeePenalty::query()->create([
            'student_id' => $student->id,
            'fee_structure_id' => $feeStructure->id,
            'penalty_type' => FeePenaltyType::LateFee,
            'status' => FeePenaltyStatus::Pending,
            'penalty_date' => now()->toDateString(),
            'base_amount' => 10000,
            'penalty_amount' => 250,
            'days_late' => 5,
            'description' => 'Late fee test',
        ]);

        $entry = app(AccountingLedgerService::class)->postPenaltyAccrual($penalty);

        $this->assertNotNull($entry);
        $this->assertSame(AccountingReferenceType::FeePenalty, $entry->reference_type);

        $lines = AccountingJournalLine::query()->where('journal_entry_id', $entry->id)->get();
        $this->assertSame(250.0, round((float) $lines->sum('debit'), 2));
        $this->assertSame(250.0, round((float) $lines->sum('credit'), 2));
    }

    public function test_fee_ledger_summary_shows_collections_as_credits(): void
    {
        $staff = User::factory()->create();
        $student = $this->createStudent();
        $feeStructure = $this->createFeeStructure($student);
        $ledger = app(AccountingLedgerService::class);

        $cashPayment = Payment::query()->create([
            'fee_structure_id' => $feeStructure->id,
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'amount' => 60000,
            'payment_mode' => PaymentMode::Cash,
            'receipt_number' => 'RCP-CASH-001',
            'proof_image_path' => 'proofs/test.jpg',
            'added_by_user_id' => $staff->id,
        ]);

        $bankPayment = Payment::query()->create([
            'fee_structure_id' => $feeStructure->id,
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'amount' => 110000,
            'payment_mode' => PaymentMode::Upi,
            'receipt_number' => 'RCP-BANK-001',
            'proof_image_path' => 'proofs/test.jpg',
            'added_by_user_id' => $staff->id,
        ]);

        $ledger->postPayment($cashPayment, 60000, 0, $staff);
        $ledger->postPayment($bankPayment, 110000, 0, $staff);

        $summary = $ledger->feeLedgerSummary();

        $this->assertSame(2, $summary['entry_count']);
        $this->assertSame(170000.0, $summary['total_collected']);
        $this->assertSame(60000.0, $summary['cash_collected']);
        $this->assertSame(110000.0, $summary['bank_collected']);
        $this->assertSame(170000.0, $summary['tuition_income']);
        $this->assertCount(2, $summary['collection_rows']);
    }

    public function test_payment_entry_is_presented_as_credit_collection(): void
    {
        $staff = User::factory()->create();
        $student = $this->createStudent();
        $feeStructure = $this->createFeeStructure($student);
        $ledger = app(AccountingLedgerService::class);

        $payment = Payment::query()->create([
            'fee_structure_id' => $feeStructure->id,
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'amount' => 10000,
            'payment_mode' => PaymentMode::Upi,
            'receipt_number' => 'RCP-PRESENT-001',
            'proof_image_path' => 'proofs/test.jpg',
            'added_by_user_id' => $staff->id,
        ]);

        $entry = $ledger->postPayment($payment, 10000, 0, $staff);
        $presented = $ledger->presentEntryLines($entry);

        $this->assertCount(1, $presented);
        $this->assertSame('credit', $presented->first()->side);
        $this->assertSame('Credit', $presented->first()->sideLabel);
        $this->assertStringContainsString('Bank / UPI', $presented->first()->label);
        $this->assertSame(10000.0, $presented->first()->amount);
        $this->assertSame('Ledger Student', $presented->first()->detail);
    }

    protected function createStudent(): Student
    {
        return Student::query()->create([
            'name' => 'Ledger Student',
            'mobile' => '9000000011',
            'status' => StudentStatus::Enrolled,
        ]);
    }

    protected function createFeeStructure(Student $student): FeeStructure
    {
        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'LEDGER-'.uniqid(),
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'ENQ-'.$student->id,
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-'.$student->id,
            'course_fee' => 10000,
            'discount_amount' => 0,
            'net_fee' => 10000,
            'use_installment_plan' => false,
            'status' => AdmissionStatus::Approved,
            'approved_at' => now(),
            'submitted_at' => now(),
        ]);

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
            'enrollment_number' => 'ENR-'.$student->id,
            'enrolled_at' => now(),
        ]);

        return FeeStructure::query()->create([
            'enrollment_id' => $enrollment->id,
            'course_fee' => 10000,
            'discount_amount' => 0,
            'net_fee' => 10000,
            'paid_amount' => 0,
            'pending_amount' => 10000,
        ]);
    }
}
