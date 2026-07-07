<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\FeeMiscChargeKind;
use App\Enums\FeeMiscChargeStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Pages\StudentProfilePage;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\EnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentProfileAdjustFeesModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_adjust_fees_modal_mounts_without_error(): void
    {
        $admin = $this->createSuperAdmin();
        $student = $this->createEnrolledStudentWithFees($admin);

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->callAction('adjustFees')
            ->assertStatus(200);
    }

    public function test_adjust_fees_modal_can_add_misc_charge(): void
    {
        $admin = $this->createSuperAdmin();
        $student = $this->createEnrolledStudentWithFees($admin);
        $feeStructure = $student->activeEnrollment->feeStructure;

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->callAction('adjustFees', data: [
                'course_fee' => (float) $feeStructure->course_fee,
                'discount_mode' => 'amount',
                'discount_adjustment' => 0,
                'reschedule_installments' => false,
                'installment_plan' => [],
                'new_misc_charges' => [
                    [
                        'label' => 'Exam fee',
                        'amount' => 1500,
                        'due_date' => null,
                    ],
                ],
                'reason' => '',
            ])
            ->assertNotified();

        $charge = $feeStructure->fresh()->miscCharges
            ->first(fn ($row) => $row->label === 'Exam fee');

        $this->assertNotNull($charge);
        $this->assertSame(FeeMiscChargeKind::Separate, $charge->kind);
        $this->assertSame(FeeMiscChargeStatus::Pending, $charge->status);
        $this->assertSame(1500.0, (float) $charge->amount);
    }

    public function test_add_misc_charge_header_action_is_not_available(): void
    {
        $admin = $this->createSuperAdmin();
        $student = $this->createEnrolledStudentWithFees($admin);

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->assertActionDoesNotExist('addMiscCharge');
    }

    protected function createSuperAdmin(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createEnrolledStudentWithFees(User $staff): Student
    {
        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'ADJ-10',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 60000,
            'status' => CourseStatus::Active,
        ]);

        $student = Student::query()->create([
            'name' => 'Adjust Fees Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '8109462945',
            'status' => StudentStatus::Enquiry,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => $student->name,
            'father_name' => $student->father_name,
            'date_of_birth' => $student->date_of_birth->toDateString(),
            'gender' => $student->gender->value,
            'mobile' => $student->mobile,
            'course_id' => $course->id,
        ], $staff, LeadSource::WalkIn);

        app(AdmissionService::class)->convert(
            $student,
            $enquiry,
            $staff,
            [
                'course_id' => $course->id,
                'discount_amount' => 0,
                'use_installment_plan' => true,
                'installment_plan' => [
                    ['label' => 'Installment 1', 'amount' => 30000, 'due_date' => now()->addMonth()->toDateString()],
                    ['label' => 'Installment 2', 'amount' => 30000, 'due_date' => now()->addMonths(2)->toDateString()],
                ],
            ],
        );

        $admissions = app(AdmissionService::class);
        $admission = $student->fresh()->admission;
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

        return $student->fresh(['activeEnrollment.feeStructure.installments']);
    }
}
