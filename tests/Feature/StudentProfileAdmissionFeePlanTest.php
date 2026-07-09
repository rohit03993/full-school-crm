<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
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
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentProfileAdmissionFeePlanTest extends TestCase
{
    use RefreshDatabase;

    public function test_set_admission_fee_plan_modal_mounts_for_pre_enrollment_admission(): void
    {
        $admin = $this->createSuperAdmin();
        $student = $this->createStudentWithAdmission($admin);

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->assertActionExists('setAdmissionFeePlan')
            ->callAction('setAdmissionFeePlan')
            ->assertStatus(200);
    }

    public function test_set_admission_fee_plan_saves_balanced_installments(): void
    {
        $admin = $this->createSuperAdmin();
        $student = $this->createStudentWithAdmission($admin);

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->callAction('setAdmissionFeePlan', data: [
                'course_id' => $student->fresh()->admission->enquiry->course_id,
                'discount_amount' => 5000,
                'misc_fees' => [
                    ['label' => 'Transport', 'amount' => 2000],
                ],
                'use_installment_plan' => true,
                'installment_plan' => [
                    ['label' => 'Admission fee', 'amount' => '23500', 'due_date' => now()->toDateString()],
                    ['label' => 'Balance', 'amount' => '23500', 'due_date' => now()->addMonth()->toDateString()],
                ],
            ])
            ->assertNotified();

        $admission = $student->fresh()->admission;

        $this->assertSame(47000.0, (float) $admission->net_fee);
        $this->assertTrue($admission->use_installment_plan);
        $this->assertCount(2, $admission->installmentPlans);
    }

    protected function createSuperAdmin(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createStudentWithAdmission(User $staff): Student
    {
        $course = Course::query()->create([
            'name' => 'Class 9',
            'code' => 'CLS-9',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $student = Student::query()->create([
            'name' => 'Fee Plan Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2011-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876501234',
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

        app(AdmissionService::class)->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
        ]);

        return $student->fresh(['admission.enquiry.course', 'admission.installmentPlans', 'admission.miscFees']);
    }
}
