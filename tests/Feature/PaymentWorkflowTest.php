<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\PaymentMode;
use App\Enums\PaymentShortfallAction;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\EnquiryService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_updates_fee_structure_and_generates_receipt_number(): void
    {
        Storage::fake('local');

        $staff = $this->createStaffUser();
        $student = $this->createStudent();
        $course = $this->createCourse();
        $enquiry = $this->createEnquiry($student, $course, $staff);

        $admissionService = app(AdmissionService::class);
        $paymentService = app(PaymentService::class);

        $admission = $admissionService->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
            'discount_amount' => 5000,
        ]);

        $admission = $admissionService->submitForm(
            $admission,
            ['tenth_board' => 'CBSE', 'tenth_percentage' => 85],
            [
                'photo' => UploadedFile::fake()->image('photo.jpg'),
                'aadhaar' => UploadedFile::fake()->create('aadhaar.pdf', 100, 'application/pdf'),
                'marksheet' => UploadedFile::fake()->create('marksheet.pdf', 100, 'application/pdf'),
                'signature' => UploadedFile::fake()->image('sign.jpg'),
            ],
            $staff,
        );

        $enrollment = $admissionService->approve($admission, $staff);
        $feeStructure = $enrollment->feeStructure;

        $this->assertSame(45000.0, (float) $feeStructure->pending_amount);
        $this->assertCount(1, $feeStructure->installments);
        $this->assertSame('Full fee', $feeStructure->installments->first()->label);

        $payment = $paymentService->add(
            $feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 10000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-001',
                'shortfall_action' => PaymentShortfallAction::NewInstallment->value,
                'shortfall_due_date' => now()->addMonth()->toDateString(),
                'shortfall_label' => 'Installment 2',
            ],
            UploadedFile::fake()->image('voucher.jpg'),
            $staff,
        );

        $feeStructure->refresh();

        $this->assertStringStartsWith('REC-', $payment->receipt_number);
        $this->assertSame(10000.0, (float) $payment->amount);
        $this->assertSame(10000.0, (float) $feeStructure->paid_amount);
        $this->assertSame(35000.0, (float) $feeStructure->pending_amount);

        $installment = $feeStructure->installments()
            ->where('paid_amount', '>', 0)
            ->first();

        $this->assertNotNull($installment);
        $this->assertSame(10000.0, (float) $installment->paid_amount);
        $this->assertSame(0.0, (float) $installment->pending_amount);
        $this->assertGreaterThanOrEqual(2, $feeStructure->installments()->count());
        Storage::disk('local')->assertExists($payment->proof_image_path);

        $payment->refresh();
        $this->assertTrue($payment->hasReceiptPdf());
        Storage::disk('local')->assertExists($payment->receipt_path);

        $enrollment->refresh();
        $this->assertTrue($enrollment->hasIdCard());
        Storage::disk('local')->assertExists($enrollment->id_card_path);

        $pdfBytes = Storage::disk('local')->get($enrollment->id_card_path);
        $this->assertGreaterThan(5000, strlen($pdfBytes));
    }

    public function test_payment_cannot_exceed_pending_amount(): void
    {
        Storage::fake('local');

        $staff = $this->createStaffUser();
        $student = $this->createStudent();
        $course = $this->createCourse();
        $enquiry = $this->createEnquiry($student, $course, $staff);

        $admissionService = app(AdmissionService::class);
        $admission = $admissionService->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
            'discount_amount' => 0,
        ]);

        $admission = $admissionService->submitForm(
            $admission,
            ['tenth_board' => 'CBSE'],
            [
                'photo' => UploadedFile::fake()->image('photo.jpg'),
                'aadhaar' => UploadedFile::fake()->create('aadhaar.pdf', 100, 'application/pdf'),
                'marksheet' => UploadedFile::fake()->create('marksheet.pdf', 100, 'application/pdf'),
                'signature' => UploadedFile::fake()->image('sign.jpg'),
            ],
            $staff,
        );

        $enrollment = $admissionService->approve($admission, $staff);

        $this->expectException(ValidationException::class);

        app(PaymentService::class)->add(
            $enrollment->feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 999999,
                'payment_mode' => PaymentMode::Upi->value,
                'utr_number' => 'UTR123',
            ],
            UploadedFile::fake()->image('proof.jpg'),
            $staff,
        );
    }

    public function test_strict_mode_rejects_payment_above_selected_installment_pending(): void
    {
        Storage::fake('local');
        config(['fees.payment.allocation' => 'strict']);

        [$student, $feeStructure, $staff] = $this->createEnrolledStudentWithInstallments();

        $firstInstallment = $feeStructure->installments()->orderBy('sort_order')->first();

        $this->expectException(ValidationException::class);

        app(PaymentService::class)->add(
            $feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 35000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-STRICT',
                'fee_installment_id' => $firstInstallment->id,
            ],
            UploadedFile::fake()->image('voucher.jpg'),
            $staff,
        );
    }

    public function test_flexible_partial_payment_rolls_shortfall_to_next_installment(): void
    {
        Storage::fake('local');
        config(['fees.payment.allocation' => 'flexible']);

        [$student, $feeStructure, $staff] = $this->createEnrolledStudentWithInstallments();

        $installments = $feeStructure->installments()->orderBy('sort_order')->orderBy('id')->get();
        $firstInstallment = $installments->first();
        $secondInstallment = $installments->last();

        app(PaymentService::class)->add(
            $feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 15000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-PARTIAL',
                'fee_installment_id' => $firstInstallment->id,
                'shortfall_action' => PaymentShortfallAction::CarryForward->value,
            ],
            UploadedFile::fake()->image('voucher.jpg'),
            $staff,
        );

        $firstInstallment->refresh();
        $secondInstallment->refresh();
        $feeStructure->refresh();

        $this->assertSame(15000.0, (float) $firstInstallment->paid_amount);
        $this->assertSame(0.0, (float) $firstInstallment->pending_amount);
        $this->assertSame(35000.0, (float) $secondInstallment->amount);
        $this->assertSame(35000.0, (float) $secondInstallment->pending_amount);
        $this->assertSame(35000.0, (float) $feeStructure->pending_amount);
    }

    public function test_flexible_overpayment_carries_forward_to_next_installment(): void
    {
        Storage::fake('local');
        config(['fees.payment.allocation' => 'flexible']);

        [$student, $feeStructure, $staff] = $this->createEnrolledStudentWithInstallments();

        $installments = $feeStructure->installments()->orderBy('sort_order')->orderBy('id')->get();
        $firstInstallment = $installments->first();
        $secondInstallment = $installments->last();

        app(PaymentService::class)->add(
            $feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 30000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-OVER',
                'fee_installment_id' => $firstInstallment->id,
            ],
            UploadedFile::fake()->image('voucher.jpg'),
            $staff,
        );

        $firstInstallment->refresh();
        $secondInstallment->refresh();
        $feeStructure->refresh();

        $this->assertSame(0.0, (float) $firstInstallment->pending_amount);
        $this->assertSame(20000.0, (float) $secondInstallment->pending_amount);
        $this->assertSame(20000.0, (float) $feeStructure->pending_amount);
    }

    public function test_partial_payment_can_create_new_installment_for_shortfall(): void
    {
        Storage::fake('local');

        [$student, $feeStructure, $staff] = $this->createEnrolledStudentWithInstallments();

        $firstInstallment = $feeStructure->installments()->orderBy('sort_order')->first();

        $payment = app(PaymentService::class)->add(
            $feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 10000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-NEW-INST',
                'fee_installment_id' => $firstInstallment->id,
                'shortfall_action' => PaymentShortfallAction::NewInstallment->value,
                'shortfall_due_date' => now()->addDays(45)->toDateString(),
                'shortfall_label' => 'Installment 1 balance',
            ],
            UploadedFile::fake()->image('voucher.jpg'),
            $staff,
        );

        $feeStructure->refresh();
        $installments = $feeStructure->installments()->orderBy('sort_order')->orderBy('id')->get();

        $this->assertCount(3, $installments);
        $this->assertNotNull($payment->shortfall_allocation);
        $this->assertSame(PaymentShortfallAction::NewInstallment->value, $payment->shortfall_allocation['action']);
        $this->assertStringContainsString('balance scheduled', $payment->shortfallSummary() ?? '');

        $shortfallInstallment = $installments->firstWhere(
            'id',
            $payment->shortfall_allocation['target_installment_id'],
        );

        $this->assertNotNull($shortfallInstallment);
        $this->assertSame(15000.0, (float) $shortfallInstallment->pending_amount);
    }

    /**
     * @return array{0: Student, 1: \App\Models\FeeStructure, 2: User}
     */
    protected function createEnrolledStudentWithInstallments(): array
    {
        $staff = $this->createStaffUser();
        $student = $this->createStudent();
        $course = $this->createCourse();
        $enquiry = $this->createEnquiry($student, $course, $staff);

        $admissionService = app(AdmissionService::class);
        $admission = $admissionService->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
            'use_installment_plan' => true,
            'installment_plan' => [
                ['label' => 'Admission fee', 'amount' => 25000, 'due_date' => now()->toDateString()],
                ['label' => 'Balance', 'amount' => 25000, 'due_date' => now()->addMonth()->toDateString()],
            ],
        ]);

        $admission = $admissionService->submitForm(
            $admission,
            ['tenth_board' => 'CBSE', 'tenth_percentage' => 85],
            [
                'photo' => UploadedFile::fake()->image('photo.jpg'),
                'aadhaar' => UploadedFile::fake()->create('aadhaar.pdf', 100, 'application/pdf'),
                'marksheet' => UploadedFile::fake()->create('marksheet.pdf', 100, 'application/pdf'),
                'signature' => UploadedFile::fake()->image('sign.jpg'),
            ],
            $staff,
        );

        $enrollment = $admissionService->approve($admission, $staff);

        return [$student, $enrollment->feeStructure, $staff];
    }

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createStudent(): Student
    {
        return Student::query()->create([
            'name' => 'Test Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => '9876543211',
            'status' => StudentStatus::Enquiry,
            'portal_password' => app(\App\Services\StudentAuthService::class)->hashPortalPassword('15052000'),
        ]);
    }

    protected function createCourse(): Course
    {
        return Course::query()->create([
            'name' => 'Diploma Test',
            'code' => 'DIP-PAY',
            'programme_category' => 'coaching',
            'duration' => 6,
            'duration_type' => 'months',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);
    }

    protected function createEnquiry(Student $student, Course $course, User $staff): \App\Models\Enquiry
    {
        return app(EnquiryService::class)->create([
            'name' => $student->name,
            'father_name' => $student->father_name,
            'date_of_birth' => $student->date_of_birth->toDateString(),
            'gender' => $student->gender->value,
            'mobile' => $student->mobile,
            'course_id' => $course->id,
        ], $staff, LeadSource::WalkIn);
    }
}
