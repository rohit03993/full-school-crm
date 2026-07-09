<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\ResultDeclarationStatus;
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
use App\Services\ResultDeclarationService;
use App\Support\PublishedResultsGate;
use App\Support\StudentExamMarksMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ResultDeclarationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_hides_marks_from_portal_until_declared(): void
    {
        Storage::fake('local');

        $this->seed(\Database\Seeders\ActivityTypeSeeder::class);

        $staff = $this->createSuperAdmin();
        $student = $this->createEnrolledStudent($staff);
        $batch = Batch::query()->where('name', 'Result Batch')->firstOrFail();
        $examType = ActivityType::query()->where('slug', 'exam')->firstOrFail();
        $attendance = app(ActivityAttendanceService::class);

        $testName = 'Unit Test — June 2026';
        $testDate = '2026-06-10';
        $groupKey = Str::slug($testName).'-'.$testDate;

        foreach (['Mathematics' => 42, 'Physics' => 38] as $subject => $score) {
            $session = ActivitySession::query()->create([
                'activity_type_id' => $examType->id,
                'title' => "{$testName} — {$subject}",
                'batch_id' => $batch->id,
                'session_date' => $testDate,
                'metadata' => [
                    'test_key' => $groupKey,
                    'test_name' => $testName,
                    'subject' => $subject,
                    'max_marks' => 50,
                ],
                'created_by_user_id' => $staff->id,
            ]);

            $attendance->importStudentScores($session, [$student->id => $score], $staff);
        }

        $records = $attendance->presentRecordsForStudent($student, $examType);
        $this->assertTrue(PublishedResultsGate::filterRecordsForPortal($records)->isEmpty());

        $declaration = app(ResultDeclarationService::class)->publish($groupKey, $staff, '2026-06-15');
        $this->assertSame(ResultDeclarationStatus::Published, $declaration->status);
        $this->assertTrue($declaration->marksAreLocked());
        $this->assertTrue(PublishedResultsGate::isPublishedGroupKey($groupKey));
        $this->assertTrue(PublishedResultsGate::marksAreLocked($groupKey));
        $this->assertFalse(PublishedResultsGate::filterRecordsForPortal($records)->isEmpty());

        $sheet = $declaration->studentMarksheets()->where('student_id', $student->id)->first();
        $this->assertNotNull($sheet?->rank);
        $this->assertArrayHasKey('attendance_percentage', $sheet->snapshot ?? []);

        $issued = app(ResultDeclarationService::class)->issueMarksheets($groupKey, $staff, '2026-06-16');
        $sheet = $issued->studentMarksheets()->where('student_id', $student->id)->first();

        $this->assertNotNull($sheet);
        $this->assertTrue($sheet->hasPdf());
        $this->assertSame($groupKey, StudentExamMarksMatrix::groupKeyForSession(
            ActivitySession::query()->where('batch_id', $batch->id)->first(),
        ));
    }

    public function test_locked_marks_block_import_after_publish(): void
    {
        $this->seed(\Database\Seeders\ActivityTypeSeeder::class);

        $staff = $this->createSuperAdmin();
        $student = $this->createEnrolledStudent($staff);
        $batch = Batch::query()->where('name', 'Result Batch')->firstOrFail();
        $examType = ActivityType::query()->where('slug', 'exam')->firstOrFail();
        $attendance = app(ActivityAttendanceService::class);
        $service = app(ResultDeclarationService::class);

        $testName = 'Unit Test — June 2026';
        $testDate = '2026-06-10';
        $groupKey = Str::slug($testName).'-'.$testDate;

        $session = ActivitySession::query()->create([
            'activity_type_id' => $examType->id,
            'title' => "{$testName} — Mathematics",
            'batch_id' => $batch->id,
            'session_date' => $testDate,
            'metadata' => [
                'test_key' => $groupKey,
                'test_name' => $testName,
                'subject' => 'Mathematics',
                'max_marks' => 50,
            ],
            'created_by_user_id' => $staff->id,
        ]);

        $attendance->importStudentScores($session, [$student->id => 40], $staff);
        $service->publish($groupKey, $staff, '2026-06-15');

        $this->expectException(ValidationException::class);
        $attendance->importStudentScores($session, [$student->id => 45], $staff);
    }

    public function test_unlock_allows_mark_edits_and_unpublish_hides_portal(): void
    {
        $this->seed(\Database\Seeders\ActivityTypeSeeder::class);

        $staff = $this->createSuperAdmin();
        $student = $this->createEnrolledStudent($staff);
        $batch = Batch::query()->where('name', 'Result Batch')->firstOrFail();
        $examType = ActivityType::query()->where('slug', 'exam')->firstOrFail();
        $attendance = app(ActivityAttendanceService::class);
        $service = app(ResultDeclarationService::class);

        $testName = 'Unit Test — June 2026';
        $testDate = '2026-06-10';
        $groupKey = Str::slug($testName).'-'.$testDate;

        $session = ActivitySession::query()->create([
            'activity_type_id' => $examType->id,
            'title' => "{$testName} — Mathematics",
            'batch_id' => $batch->id,
            'session_date' => $testDate,
            'metadata' => [
                'test_key' => $groupKey,
                'test_name' => $testName,
                'subject' => 'Mathematics',
                'max_marks' => 50,
            ],
            'created_by_user_id' => $staff->id,
        ]);

        $attendance->importStudentScores($session, [$student->id => 40], $staff);
        $service->publish($groupKey, $staff, '2026-06-15');

        $service->unlockMarks($groupKey, $staff);
        $this->assertFalse(PublishedResultsGate::marksAreLocked($groupKey));

        $attendance->importStudentScores($session, [$student->id => 44], $staff);
        $this->assertSame(44.0, (float) $attendance->scoresFor($session)[$student->id]['marks_obtained']);

        $service->unpublish($groupKey, $staff);
        $this->assertFalse(PublishedResultsGate::isPublishedGroupKey($groupKey));
        $this->assertFalse(PublishedResultsGate::marksAreLocked($groupKey));
    }

    public function test_regenerate_marksheets_refreshes_pdf_paths(): void
    {
        Storage::fake('local');

        $this->seed(\Database\Seeders\ActivityTypeSeeder::class);

        $staff = $this->createSuperAdmin();
        $student = $this->createEnrolledStudent($staff);
        $batch = Batch::query()->where('name', 'Result Batch')->firstOrFail();
        $examType = ActivityType::query()->where('slug', 'exam')->firstOrFail();
        $attendance = app(ActivityAttendanceService::class);

        $testName = 'Unit Test — June 2026';
        $testDate = '2026-06-10';
        $groupKey = Str::slug($testName).'-'.$testDate;

        foreach (['Mathematics' => 42] as $subject => $score) {
            $session = ActivitySession::query()->create([
                'activity_type_id' => $examType->id,
                'title' => "{$testName} — {$subject}",
                'batch_id' => $batch->id,
                'session_date' => $testDate,
                'metadata' => [
                    'test_key' => $groupKey,
                    'test_name' => $testName,
                    'subject' => $subject,
                    'max_marks' => 50,
                ],
                'created_by_user_id' => $staff->id,
            ]);

            $attendance->importStudentScores($session, [$student->id => $score], $staff);
        }

        $service = app(ResultDeclarationService::class);
        $service->publish($groupKey, $staff, '2026-06-15');
        $service->issueMarksheets($groupKey, $staff, '2026-06-16');

        $sheet = $service->findForGroupKey($groupKey)?->studentMarksheets()->where('student_id', $student->id)->first();
        $this->assertNotNull($sheet);
        $originalPath = $sheet->pdf_path;
        $this->assertTrue($sheet->hasPdf());

        $service->regenerateMarksheets($groupKey, $staff, '2026-06-17');

        $sheet->refresh();
        $this->assertTrue($sheet->hasPdf());
        $this->assertNotNull($sheet->pdf_path);
        $this->assertSame('2026-06-17', $service->findForGroupKey($groupKey)?->marksheet_issue_date?->toDateString());
    }

    public function test_issue_marksheets_rejects_when_already_issued(): void
    {
        Storage::fake('local');

        $this->seed(\Database\Seeders\ActivityTypeSeeder::class);

        $staff = $this->createSuperAdmin();
        $student = $this->createEnrolledStudent($staff);
        $batch = Batch::query()->where('name', 'Result Batch')->firstOrFail();
        $examType = ActivityType::query()->where('slug', 'exam')->firstOrFail();
        $attendance = app(ActivityAttendanceService::class);

        $testName = 'Unit Test — June 2026';
        $testDate = '2026-06-10';
        $groupKey = Str::slug($testName).'-'.$testDate;

        $session = ActivitySession::query()->create([
            'activity_type_id' => $examType->id,
            'title' => "{$testName} — Mathematics",
            'batch_id' => $batch->id,
            'session_date' => $testDate,
            'metadata' => [
                'test_key' => $groupKey,
                'test_name' => $testName,
                'subject' => 'Mathematics',
                'max_marks' => 50,
            ],
            'created_by_user_id' => $staff->id,
        ]);

        $attendance->importStudentScores($session, [$student->id => 40], $staff);

        $service = app(ResultDeclarationService::class);
        $service->publish($groupKey, $staff, '2026-06-15');
        $service->issueMarksheets($groupKey, $staff, '2026-06-16');

        $this->expectException(ValidationException::class);
        $service->issueMarksheets($groupKey, $staff, '2026-06-18');
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
        $student = Student::query()->create([
            'name' => 'Result Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => '9876543299',
            'status' => StudentStatus::Enquiry,
            'portal_password' => app(\App\Services\StudentAuthService::class)->hashPortalPassword('15052000'),
        ]);

        $course = Course::query()->create([
            'name' => 'Demo Course',
            'code' => 'RES-101',
            'programme_category' => 'coaching',
            'duration' => 6,
            'duration_type' => 'months',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $batch = Batch::query()->create([
            'name' => 'Result Batch',
            'course_id' => $course->id,
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
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
        app(BatchService::class)->assign($student->fresh(), $batch, $staff);

        return $student->fresh();
    }
}
