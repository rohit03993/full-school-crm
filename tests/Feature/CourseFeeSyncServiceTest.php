<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\CourseFeeSyncService;
use App\Services\EnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourseFeeSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_fee_increase_syncs_active_enrollment_and_reschedules_installments(): void
    {
        Storage::fake('local');

        $staff = $this->createStaffUser();
        $student = $this->createStudent();
        $course = $this->createCourse(225000);
        $enquiry = $this->createEnquiry($student, $course, $staff);

        $admissionService = app(AdmissionService::class);
        $admission = $admissionService->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
            'discount_amount' => 5000,
            'use_installment_plan' => true,
            'installment_plan' => [
                ['label' => 'Installment 1', 'amount' => 110000, 'due_date' => now()->toDateString()],
                ['label' => 'Installment 2', 'amount' => 110000, 'due_date' => now()->addMonth()->toDateString()],
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
        $feeStructure = $enrollment->feeStructure->fresh(['installments']);

        $this->assertSame(225000.0, (float) $feeStructure->course_fee);
        $this->assertSame(5000.0, (float) $feeStructure->discount_amount);
        $this->assertSame(220000.0, (float) $feeStructure->net_fee);

        $course->update(['fee' => 230000]);

        $synced = app(CourseFeeSyncService::class)->syncCourseToActiveEnrollments($course->fresh(), $staff);

        $this->assertSame(1, $synced);

        $feeStructure = $feeStructure->fresh(['installments']);

        $this->assertSame(230000.0, (float) $feeStructure->course_fee);
        $this->assertSame(5000.0, (float) $feeStructure->discount_amount);
        $this->assertSame(225000.0, (float) $feeStructure->net_fee);
        $this->assertSame(225000.0, (float) $feeStructure->pending_amount);
        $this->assertGreaterThan(0, $feeStructure->installments->where('pending_amount', '>', 0)->count());
        $this->assertEqualsWithDelta(
            225000.0,
            (float) $feeStructure->installments->where('pending_amount', '>', 0)->sum('pending_amount'),
            0.01,
        );
    }

    private function createStaffUser(): User
    {
        $role = Role::findOrCreate(RoleName::SuperAdmin->value);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    private function createStudent(): Student
    {
        return Student::query()->create([
            'name' => 'Sync Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876543210',
            'status' => StudentStatus::Enquiry,
        ]);
    }

    private function createCourse(float $fee): Course
    {
        return Course::query()->create([
            'name' => 'BSc Hotel Management',
            'code' => 'HM-BSC',
            'duration' => 3,
            'duration_type' => 'years',
            'fee' => $fee,
            'status' => CourseStatus::Active,
        ]);
    }

    private function createEnquiry(Student $student, Course $course, User $staff)
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
