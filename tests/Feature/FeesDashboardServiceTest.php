<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\PaymentMode;
use App\Models\Course;
use App\Models\FeeInstallment;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\EnquiryService;
use App\Services\FeesDashboardService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeesDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_counts_overdue_installments_and_collections(): void
    {
        Storage::fake('local');

        $staff = $this->createSuperAdmin();
        $student = $this->enrollStudentWithInstallments($staff, now()->subDays(10)->toDateString());
        $feeStructure = $student->fresh()->activeEnrollment?->feeStructure;
        $this->assertNotNull($feeStructure);

        $summaryBeforePayment = app(FeesDashboardService::class)->summary();
        $this->assertGreaterThan(0, $summaryBeforePayment['overdue_installment_count']);
        $this->assertGreaterThan(0, $summaryBeforePayment['overdue_students_count']);
        $this->assertGreaterThan(0, $summaryBeforePayment['pending_fees_total']);

        $installment = $feeStructure->installments()->orderBy('sort_order')->first();
        $this->assertNotNull($installment);

        app(PaymentService::class)->add(
            $feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => (float) $installment->pending_amount,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-DASH-1',
                'fee_installment_id' => $installment->id,
            ],
            UploadedFile::fake()->image('voucher.jpg'),
            $staff,
        );

        $summary = app(FeesDashboardService::class)->summary();

        $this->assertSame((float) $installment->pending_amount, $summary['collection_today']);
    }

    public function test_fee_status_and_next_due_for_student_with_overdue_installment(): void
    {
        $staff = $this->createSuperAdmin();
        $student = $this->enrollStudentWithInstallments($staff, now()->subDays(5)->toDateString());
        $service = app(FeesDashboardService::class);

        $status = $service->feeStatusForStudent($student->fresh(['activeEnrollment.feeStructure.installments']));

        $this->assertSame('Overdue', $status['label']);
        $this->assertSame('danger', $status['color']);
        $this->assertNotNull($service->nextDueDateForStudent($student->fresh(['activeEnrollment.feeStructure.installments'])));
    }

    public function test_defaulters_lists_student_with_overdue_balance(): void
    {
        $staff = $this->createSuperAdmin();
        $student = $this->enrollStudentWithInstallments($staff, now()->subDays(7)->toDateString());

        $defaulters = app(FeesDashboardService::class)->defaulters();

        $this->assertTrue($defaulters->contains(fn (array $row): bool => $row['student_id'] === $student->id));
    }

    protected function createSuperAdmin(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function enrollStudentWithInstallments(User $staff, string $firstDueDate): Student
    {
        $student = Student::query()->create([
            'name' => 'Fees Dashboard Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => '9876543288',
            'status' => StudentStatus::Enquiry,
            'portal_password' => app(\App\Services\StudentAuthService::class)->hashPortalPassword('15052000'),
        ]);

        $course = Course::query()->create([
            'name' => 'Fees Dashboard Course',
            'code' => 'FEE-DASH',
            'programme_category' => 'coaching',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 60000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => $student->name,
            'father_name' => $student->father_name,
            'date_of_birth' => $student->date_of_birth->toDateString(),
            'gender' => $student->gender->value,
            'mobile' => $student->mobile,
            'course_id' => $course->id,
        ], $staff, LeadSource::WalkIn);

        $admissions = app(AdmissionService::class);
        $admission = $admissions->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
            'discount_amount' => 0,
            'use_installment_plan' => true,
            'installment_plan' => [
                ['label' => 'First', 'amount' => 30000, 'due_date' => $firstDueDate],
                ['label' => 'Second', 'amount' => 30000, 'due_date' => now()->addDays(90)->toDateString()],
            ],
        ]);

        $admission = $admissions->submitForm(
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

        $admissions->approve($admission, $staff);

        $installment = FeeInstallment::query()
            ->whereHas('feeStructure.enrollment', fn ($query) => $query->where('student_id', $student->id))
            ->orderBy('sort_order')
            ->first();

        $this->assertNotNull($installment);
        $this->assertGreaterThan(0, (float) $installment->pending_amount);

        return $student->fresh();
    }
}
