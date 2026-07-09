<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\FeeMiscChargeKind;
use App\Enums\FeeMiscChargeStatus;
use App\Enums\Gender;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Pages\StudentProfilePage;
use App\Models\Course;
use App\Models\FeeMiscCharge;
use App\Models\FeeMiscChargeAdjustmentRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\EnquiryService;
use App\Enums\LeadSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentProfileFeesTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_fees_tab_renders_for_enrolled_student(): void
    {
        $admin = $this->createSuperAdmin();
        $student = $this->createEnrolledStudentWithFees($admin);

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->set('profileTab', 'fees')
            ->assertSet('profileTab', 'fees')
            ->assertSet('feesTabLoaded', true)
            ->assertStatus(200)
            ->assertSee('Payment schedule');
    }

    public function test_fees_tab_renders_with_separate_misc_charge(): void
    {
        $admin = $this->createSuperAdmin();
        $student = $this->createEnrolledStudentWithFees($admin);

        FeeMiscCharge::query()->create([
            'fee_structure_id' => $student->activeEnrollment->feeStructure->id,
            'label' => 'Hostel',
            'amount' => 5000,
            'paid_amount' => 0,
            'kind' => FeeMiscChargeKind::Separate,
            'status' => FeeMiscChargeStatus::Pending,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->set('profileTab', 'fees')
            ->assertStatus(200)
            ->assertSee('Hostel');
    }

    public function test_fees_tab_renders_with_misc_charge_adjustment_request(): void
    {
        $admin = $this->createSuperAdmin();
        $student = $this->createEnrolledStudentWithFees($admin);
        $charge = FeeMiscCharge::query()->create([
            'fee_structure_id' => $student->activeEnrollment->feeStructure->id,
            'label' => 'Hostel',
            'amount' => 5000,
            'paid_amount' => 0,
            'kind' => FeeMiscChargeKind::Separate,
            'status' => FeeMiscChargeStatus::Pending,
            'sort_order' => 1,
        ]);

        FeeMiscChargeAdjustmentRequest::query()->create([
            'fee_misc_charge_id' => $charge->id,
            'requested_by_user_id' => $admin->id,
            'type' => 'waive_off',
            'reason' => 'Management approved',
            'status' => 'pending',
        ]);

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->set('profileTab', 'fees')
            ->assertStatus(200)
            ->assertSee('Waive off pending');
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
            'name' => 'Class 12',
            'code' => 'FEE-12',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 60000,
            'status' => CourseStatus::Active,
        ]);

        $student = Student::query()->create([
            'name' => 'Fees Tab Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '8109462946',
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
