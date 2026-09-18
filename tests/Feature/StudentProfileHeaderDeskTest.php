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

class StudentProfileHeaderDeskTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_profile_keeps_payment_and_edit_and_hides_back_to_search(): void
    {
        $admin = $this->createSuperAdmin();
        $student = $this->createEnrolledStudentWithFees($admin);

        $this->actingAs($admin);

        $page = Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->assertSuccessful()
            ->assertDontSee('Back to Search')
            ->assertDontSeeHtml('fi-header-heading')
            ->assertSeeHtml('fi-student-profile-desk-actions')
            ->assertSee('Add Payment');

        $this->assertSame('', $page->instance()->getHeading());
        $this->assertSame($student->name, $page->instance()->getTitle());

        $page
            ->assertSee('More')
            ->assertSee('Fees due')
            ->assertActionVisible('addPayment')
            ->assertActionVisible('editStudent')
            ->assertActionVisible('deleteStudent')
            ->assertActionVisible('assignBatch')
            ->call('openActivityTimelineTab', 'fees')
            ->assertSet('profileTab', 'fees');
    }

    public function test_lead_profile_keeps_add_visit_as_a_primary_action(): void
    {
        $admin = $this->createSuperAdmin();
        $student = $this->createEnquiryStudent($admin);

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->assertSuccessful()
            ->assertDontSee('Back to Search')
            ->assertSee('Add Visit')
            ->assertSeeHtml('fi-student-profile-desk-actions')
            ->assertSee('More')
            ->assertActionVisible('addVisit')
            ->assertActionVisible('editStudent');
    }

    protected function createSuperAdmin(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createEnquiryStudent(User $staff): Student
    {
        $course = Course::query()->create([
            'name' => 'Class 11 Science',
            'code' => 'HDR-11',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 40000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Header Lead',
            'father_name' => 'Parent',
            'date_of_birth' => '2008-01-01',
            'gender' => Gender::Male->value,
            'mobile' => '9876504444',
            'course_id' => $course->id,
        ], $staff, LeadSource::WalkIn);

        $student = $enquiry->student;
        $this->assertSame(StudentStatus::Enquiry, $student->status);

        return $student;
    }

    protected function createEnrolledStudentWithFees(User $staff): Student
    {
        $course = Course::query()->create([
            'name' => 'Class 12',
            'code' => 'HDR-12',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 60000,
            'status' => CourseStatus::Active,
        ]);

        $this->createBatchForCourse($course);

        $student = Student::query()->create([
            'name' => 'Header Enrolled',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '8109462955',
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

        return $student->fresh(['activeEnrollment.feeStructure']);
    }
}
