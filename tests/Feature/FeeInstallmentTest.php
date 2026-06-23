<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\PaymentMode;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\FeeInstallment;
use App\Models\FeePenalty;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\EnquiryService;
use App\Services\PaymentService;
use App\Services\PenaltyCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeeInstallmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_installments_are_created_from_admission_plan_on_approval(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createStudent();
        $course = $this->createCourse();
        $enquiry = $this->createEnquiry($student, $course, $staff);

        $admissionService = app(AdmissionService::class);
        $admission = $admissionService->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
            'discount_amount' => 0,
            'use_installment_plan' => true,
            'misc_fees' => [
                ['label' => 'Transport', 'amount' => 2000],
            ],
            'installment_plan' => [
                ['label' => 'Admission fee', 'amount' => 31000, 'due_date' => now()->toDateString()],
                ['label' => 'Balance', 'amount' => 31000, 'due_date' => now()->addDays(90)->toDateString()],
            ],
        ]);

        $this->assertSame(62000.0, (float) $admission->net_fee);
        $this->assertCount(1, $admission->miscFees);
        $this->assertCount(2, $admission->installmentPlans);

        $admission = $this->submitAdmissionForm($admission, $staff);
        $enrollment = $admissionService->approve($admission, $staff);
        $feeStructure = $enrollment->feeStructure;

        $installments = FeeInstallment::query()
            ->where('fee_structure_id', $feeStructure->id)
            ->orderBy('sort_order')
            ->get();

        $this->assertCount(2, $installments);
        $this->assertSame('Installment 1', $installments[0]->label);
        $this->assertSame(31000.0, (float) $installments[0]->amount);
        $this->assertSame('Installment 2', $installments[1]->label);
        $this->assertSame(31000.0, (float) $installments[1]->amount);
        $this->assertSame(2000.0, $feeStructure->miscChargesTotal());
    }

    public function test_payment_allocates_to_first_payable_installment(): void
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
            'use_installment_plan' => true,
            'installment_plan' => [
                ['label' => 'Admission fee', 'amount' => 30000, 'due_date' => now()->toDateString()],
                ['label' => 'Balance', 'amount' => 30000, 'due_date' => now()->addDays(90)->toDateString()],
            ],
        ]);

        $admission = $this->submitAdmissionForm($admission, $staff);
        $enrollment = $admissionService->approve($admission, $staff);
        $feeStructure = $enrollment->feeStructure;

        $installments = $feeStructure->installments()->orderBy('sort_order')->orderBy('id')->get();
        $firstInstallment = $installments->first();
        $secondInstallment = $installments->last();

        $payment = app(PaymentService::class)->add(
            $feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 30000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-100',
            ],
            UploadedFile::fake()->image('voucher.jpg'),
            $staff,
        );

        $firstInstallment->refresh();
        $secondInstallment->refresh();
        $feeStructure->refresh();

        $this->assertSame($firstInstallment->id, $payment->fee_installment_id);
        $this->assertSame(0.0, (float) $firstInstallment->pending_amount);
        $this->assertSame(30000.0, (float) $secondInstallment->pending_amount);
        $this->assertSame(30000.0, (float) $feeStructure->pending_amount);
    }

    public function test_late_fee_is_generated_for_overdue_installment(): void
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
                ['label' => 'Term 1', 'amount' => 60000, 'due_date' => now()->subDays(20)->toDateString()],
            ],
        ]);

        $enrollment = $admissionService->approve($this->submitAdmissionForm($admission, $staff), $staff);
        $installment = $enrollment->feeStructure->installments()->first();

        config(['fees.late_fee.grace_days' => 7, 'fees.late_fee.daily_rate' => 0.0015]);

        $penalty = app(PenaltyCalculationService::class)->processInstallmentPenalty($installment, now());

        $this->assertNotNull($penalty);
        $this->assertSame(1, FeePenalty::query()->count());
        $this->assertGreaterThan(0, (float) $penalty->penalty_amount);
    }

    protected function createCourse(): Course
    {
        return Course::query()->create([
            'name' => 'Installment Test Course',
            'code' => 'INST-TEST',
            'programme_category' => 'coaching',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 60000,
            'status' => CourseStatus::Active,
        ]);
    }

    protected function submitAdmissionForm(\App\Models\Admission $admission, User $staff): \App\Models\Admission
    {
        return app(AdmissionService::class)->submitForm(
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
            'name' => 'Installment Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => '9876543299',
            'status' => StudentStatus::Enquiry,
            'portal_password' => app(\App\Services\StudentAuthService::class)->hashPortalPassword('15052000'),
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
