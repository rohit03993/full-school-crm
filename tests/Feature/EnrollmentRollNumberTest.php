<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\EnrollmentRollNumberService;
use App\Services\EnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EnrollmentRollNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_roll_number_can_be_updated_when_unique(): void
    {
        Storage::fake('local');

        $staff = $this->createStaffUser();
        $enrollment = $this->createApprovedEnrollment($staff);

        $updated = app(EnrollmentRollNumberService::class)->update(
            $enrollment,
            'ROLL-2026-9999',
            $staff,
        );

        $this->assertSame('ROLL-2026-9999', $updated->enrollment_number);
        $this->assertDatabaseHas('enrollments', [
            'id' => $enrollment->id,
            'enrollment_number' => 'ROLL-2026-9999',
        ]);
    }

    public function test_duplicate_roll_number_is_rejected(): void
    {
        $staff = $this->createStaffUser();
        $first = $this->createApprovedEnrollment($staff, '9111111111');
        $second = $this->createApprovedEnrollment($staff, '9222222222');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(EnrollmentRollNumberService::class)->update(
            $second,
            $first->enrollment_number,
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

    protected function createApprovedEnrollment(User $staff, string $mobile = '9876543210'): Enrollment
    {
        $student = Student::query()->create([
            'name' => 'Roll Test '.$mobile,
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => $mobile,
            'status' => StudentStatus::Enquiry,
            'portal_password' => app(\App\Services\StudentAuthService::class)->hashPortalPassword('15052000'),
        ]);

        $course = Course::query()->create([
            'name' => 'Diploma Test',
            'code' => 'DIP-'.$mobile,
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

        $admissionService = app(AdmissionService::class);

        $admission = $admissionService->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
            'discount_amount' => 0,
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

        return $admissionService->approve($admission, $staff);
    }
}
