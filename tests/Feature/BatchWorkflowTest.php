<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\AttendanceService;
use App\Services\BatchService;
use App\Services\EnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BatchWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_assignment_deactivates_previous_batch(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createEnrolledStudent($staff);
        $course = Course::query()->firstOrFail();

        $batchA = $this->createBatch($course, $staff, 'Batch A');
        $batchB = $this->createBatch($course, $staff, 'Batch B');

        $batches = app(BatchService::class);

        $batches->assign($student, $batchA, $staff);
        $batches->assign($student, $batchB, $staff);

        $this->assertTrue($student->fresh()->hasActiveBatch());
        $this->assertSame($batchB->id, $student->fresh()->activeBatchStudent?->batch_id);

        $this->assertDatabaseHas('batch_students', [
            'student_id' => $student->id,
            'batch_id' => $batchA->id,
            'is_active' => false,
        ]);

        $this->assertDatabaseHas('batch_students', [
            'student_id' => $student->id,
            'batch_id' => $batchB->id,
            'is_active' => true,
        ]);

        $this->assertSame(2, BatchStudent::query()->where('student_id', $student->id)->count());
    }

    public function test_attendance_percentage_is_based_on_present_records(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createEnrolledStudent($staff);
        $course = Course::query()->firstOrFail();
        $batch = $this->createBatch($course, $staff, 'Morning Batch');

        app(BatchService::class)->assign($student, $batch, $staff);

        $attendance = app(AttendanceService::class);

        $attendance->saveBatchAttendance($batch, '2026-06-01', [
            $student->id => AttendanceStatus::Present->value,
        ], $staff);

        $attendance->saveBatchAttendance($batch, '2026-06-02', [
            $student->id => AttendanceStatus::Absent->value,
        ], $staff);

        $percentage = $attendance->percentageForStudent($student->fresh());

        $this->assertSame(50.0, $percentage);
    }

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createEnrolledStudent(User $staff): Student
    {
        Storage::fake('local');

        $student = Student::query()->create([
            'name' => 'Batch Test Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => '9876543212',
            'status' => StudentStatus::Enquiry,
            'portal_password' => app(\App\Services\StudentAuthService::class)->hashPortalPassword('15052000'),
        ]);

        $course = Course::query()->create([
            'name' => 'Diploma Batch',
            'code' => 'DIP-BAT',
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

        return $student->fresh();
    }

    protected function createBatch(Course $course, User $trainer, string $name): Batch
    {
        return Batch::query()->create([
            'name' => $name,
            'course_id' => $course->id,
            'trainer_user_id' => $trainer->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);
    }
}
