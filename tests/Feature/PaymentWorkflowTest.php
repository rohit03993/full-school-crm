<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\PaymentMode;
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

        $payment = $paymentService->add(
            $feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 10000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-001',
            ],
            UploadedFile::fake()->image('voucher.jpg'),
            $staff,
        );

        $feeStructure->refresh();

        $this->assertStringStartsWith('REC-', $payment->receipt_number);
        $this->assertSame(10000.0, (float) $payment->amount);
        $this->assertSame(10000.0, (float) $feeStructure->paid_amount);
        $this->assertSame(35000.0, (float) $feeStructure->pending_amount);
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

        $this->expectException(\Illuminate\Validation\ValidationException::class);

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

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::Staff->value);

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
            'course_type' => 'diploma',
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
