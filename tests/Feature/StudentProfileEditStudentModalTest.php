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
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentProfileEditStudentModalTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_save_alternate_mobile_from_edit_modal(): void
    {
        $admin = $this->createSuperAdmin();
        $student = $this->createEnrolledStudent($admin);

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->callAction('editStudent', data: [
                'name' => $student->name,
                'father_name' => $student->father_name,
                'date_of_birth' => $student->date_of_birth->toDateString(),
                'gender' => $student->gender->value,
                'mobile' => $student->mobile,
                'alternate_mobile' => '9859788979',
                'address' => null,
                'city' => null,
                'state' => null,
                'pincode' => null,
                'category' => $student->category->value,
                'course_id' => $student->activeEnrollment->course_id,
                'batch_id' => null,
                'enrollment_number' => $student->activeEnrollment->enrollment_number,
                'custom_data' => [],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $this->assertSame('9859788979', $student->fresh()->alternate_mobile);
    }

    protected function createSuperAdmin(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createEnrolledStudent(User $staff): Student
    {
        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'EDIT-10',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 60000,
            'status' => CourseStatus::Active,
        ]);

        $student = Student::query()->create([
            'name' => 'Aman sharma',
            'father_name' => 'varun',
            'date_of_birth' => '2010-07-03',
            'gender' => Gender::Male,
            'mobile' => '8109462946',
            'status' => StudentStatus::Enquiry,
            'category' => 'sc',
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

        return $student->fresh(['activeEnrollment']);
    }
}
