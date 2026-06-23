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
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentPortalFinancialTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_can_view_fees_and_download_own_receipt(): void
    {
        Storage::fake('local');

        $staff = $this->createStaffUser();
        $student = $this->createEnrolledStudent($staff);

        $payment = app(PaymentService::class)->add(
            $student->activeEnrollment->feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 10000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-PORTAL-1',
                'shortfall_action' => PaymentShortfallAction::NewInstallment->value,
                'shortfall_due_date' => now()->addMonth()->toDateString(),
                'shortfall_label' => 'Installment 2',
            ],
            UploadedFile::fake()->image('proof.jpg'),
            $staff,
        );

        $this->post(route('portal.login.submit'), [
            'mobile' => $student->mobile,
            'password' => config('institute.portal_default_password'),
        ])->assertRedirect(route('portal.dashboard'));

        $this->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Fee summary')
            ->assertSee('₹40,000')
            ->assertSee($payment->receipt_number)
            ->assertSee('Receipt');

        $this->get(route('portal.receipts.download', $payment))
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_student_cannot_download_another_students_receipt(): void
    {
        Storage::fake('local');

        $staff = $this->createStaffUser();
        $studentA = $this->createEnrolledStudent($staff);
        $studentB = $this->createEnrolledStudent($staff, mobile: '9876543299');

        $payment = app(PaymentService::class)->add(
            $studentA->activeEnrollment->feeStructure,
            $studentA,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 5000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-A',
                'shortfall_action' => PaymentShortfallAction::NewInstallment->value,
                'shortfall_due_date' => now()->addMonth()->toDateString(),
                'shortfall_label' => 'Installment 2',
            ],
            UploadedFile::fake()->image('proof.jpg'),
            $staff,
        );

        $this->post(route('portal.login.submit'), [
            'mobile' => $studentB->mobile,
            'password' => config('institute.portal_default_password'),
        ]);

        $this->get(route('portal.receipts.download', $payment))->assertForbidden();
    }

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createEnrolledStudent(User $staff, string $mobile = '9876543213'): Student
    {
        $student = Student::query()->create([
            'name' => 'Portal Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => $mobile,
            'status' => StudentStatus::Enquiry,
            'portal_password' => app(\App\Services\StudentAuthService::class)->hashForNewStudent(),
        ]);

        $course = Course::query()->create([
            'name' => 'Diploma Portal',
            'code' => 'DIP-PORT-'.$mobile,
            'programme_category' => 'coaching',
            'duration' => 6,
            'duration_type' => 'months',
            'fee' => 50000,
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

        return $student->fresh(['activeEnrollment.feeStructure']);
    }
}
