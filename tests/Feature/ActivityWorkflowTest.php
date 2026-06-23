<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\ActivityAttendanceService;
use App\Services\AdmissionService;
use App\Services\BatchService;
use App\Services\EnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivityWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_attendance_records_present_participation(): void
    {
        Storage::fake('local');

        $this->seed(\Database\Seeders\ActivityTypeSeeder::class);

        $staff = $this->createStaffUser();
        $student = $this->createEnrolledStudent($staff);
        $course = Course::query()->firstOrFail();
        $batch = Batch::query()->create([
            'name' => 'Activity Batch',
            'course_id' => $course->id,
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);

        app(BatchService::class)->assign($student, $batch, $staff);

        $examType = ActivityType::query()->where('slug', 'exam')->firstOrFail();

        $session = ActivitySession::query()->create([
            'activity_type_id' => $examType->id,
            'title' => 'Unit Test — Mathematics',
            'batch_id' => $batch->id,
            'session_date' => '2026-06-10',
            'metadata' => ['subject' => 'Mathematics', 'max_marks' => 100],
            'created_by_user_id' => $staff->id,
        ]);

        $service = app(ActivityAttendanceService::class);
        $saved = $service->saveMarks($session, [$student->id => true], $staff);

        $this->assertSame(1, $saved);
        $this->assertSame(1, $service->presentCountForStudent($student->fresh(), $examType));

        $this->assertDatabaseHas('activity_attendances', [
            'attendable_type' => ActivitySession::class,
            'attendable_id' => $session->id,
            'student_id' => $student->id,
            'is_present' => true,
        ]);
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
        $student = Student::query()->create([
            'name' => 'Activity Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => '9876543215',
            'status' => StudentStatus::Enquiry,
            'portal_password' => app(\App\Services\StudentAuthService::class)->hashPortalPassword('15052000'),
        ]);

        $course = Course::query()->create([
            'name' => 'Class 12 Science',
            'code' => 'DIP-ACT',
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
}
