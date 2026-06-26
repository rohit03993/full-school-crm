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
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\ActivityMarksBulkImportService;
use App\Services\ActivityMarksWhatsAppService;
use App\Services\AdmissionService;
use App\Services\BatchService;
use App\Services\EnquiryService;
use App\Services\WhatsAppTemplateParamResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ActivityMarksBulkImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_matches_students_by_roll_without_batch_filter(): void
    {
        Storage::fake('local');

        $staff = $this->createStaffUser();
        $student = $this->createEnrolledStudent($staff, '201', '9876543201');
        $course = Course::query()->firstOrFail();

        $batchA = Batch::query()->create([
            'name' => 'Batch A',
            'course_id' => $course->id,
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);

        app(BatchService::class)->assign($student, $batchA, $staff);

        $activityType = ActivityType::query()->create([
            'name' => 'Unit Test',
            'field_schema' => [
                ['key' => 'subject', 'label' => 'Subject', 'type' => 'text'],
                ['key' => 'max_marks', 'label' => 'Max Marks', 'type' => 'number'],
            ],
            'is_enabled' => true,
        ]);

        $headers = ['Roll Number', 'Mathematics', 'Physics'];
        $rows = [['201', '42', '38']];
        $mapping = ['roll_column' => 0, 'subject_columns' => [1, 2]];

        $service = app(ActivityMarksBulkImportService::class);
        $preview = $service->buildPreview($headers, $rows, $mapping, null, null, 100);

        $this->assertSame(1, $preview['ready_count']);
        $this->assertSame(0, $preview['error_count']);

        $result = $service->import(
            $staff,
            $activityType,
            'Unit Test March 20',
            '2026-03-20',
            100,
            $preview['rows'],
        );

        $this->assertSame(2, $result['marks_saved']);
        $this->assertSame(1, $result['students']);
        $this->assertSame(2, $result['sessions_created']);

        $this->assertDatabaseHas('activity_attendances', [
            'student_id' => $student->id,
            'marks_obtained' => 42,
        ]);

        $sessions = ActivitySession::query()
            ->where('metadata->test_key', $result['test_key'])
            ->get();

        $this->assertCount(2, $sessions);
    }

    public function test_optional_batch_filter_rejects_other_batches(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createEnrolledStudent($staff, '301', '9876543301');
        $course = Course::query()->firstOrFail();

        $assignedBatch = Batch::query()->create([
            'name' => 'Assigned Batch',
            'course_id' => $course->id,
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);

        $otherBatch = Batch::query()->create([
            'name' => 'Other Batch',
            'course_id' => $course->id,
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);

        app(BatchService::class)->assign($student, $assignedBatch, $staff);

        $preview = app(ActivityMarksBulkImportService::class)->buildPreview(
            ['Roll Number', 'Maths'],
            [['301', '40']],
            ['roll_column' => 0, 'subject_columns' => [1]],
            null,
            $otherBatch->id,
            100,
        );

        $this->assertSame(0, $preview['ready_count']);
        $this->assertSame(1, $preview['error_count']);
    }

    public function test_whatsapp_marks_summary_is_built_per_student(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createEnrolledStudent($staff, '401', '9876543401');
        $course = Course::query()->firstOrFail();

        $batch = Batch::query()->create([
            'name' => 'WA Batch',
            'course_id' => $course->id,
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);

        app(BatchService::class)->assign($student, $batch, $staff);

        $activityType = ActivityType::query()->create([
            'name' => 'Exam',
            'field_schema' => [
                ['key' => 'subject', 'label' => 'Subject', 'type' => 'text'],
                ['key' => 'max_marks', 'label' => 'Max Marks', 'type' => 'number'],
            ],
            'is_enabled' => true,
        ]);

        $import = app(ActivityMarksBulkImportService::class);
        $preview = $import->buildPreview(
            ['Roll Number', 'Maths', 'Physics'],
            [['401', '45', '38']],
            ['roll_column' => 0, 'subject_columns' => [1, 2]],
            null,
            null,
            50,
            [1 => 50, 2 => 50],
        );

        $result = $import->import(
            $staff,
            $activityType,
            'Half Yearly',
            '2026-03-20',
            50,
            $preview['rows'],
            $preview['subject_max_marks'],
        );

        $summaries = app(ActivityMarksWhatsAppService::class)->buildStudentMarksSummaries($result['test_key']);

        $this->assertStringContainsString('Maths: 45/50', $summaries[$student->id]);
        $this->assertStringContainsString('Physics: 38/50', $summaries[$student->id]);

        $template = WhatsAppTemplate::query()->create([
            'name' => 'marks_update',
            'param_count' => 4,
            'param_mappings' => [
                'student.name',
                'student.enrollment_number',
                'activity.test_name',
                'activity.marks_summary',
            ],
            'body' => 'Hi {{1}}, Roll {{2}} — {{3}}: {{4}}',
            'is_active' => true,
        ]);

        $campaign = app(ActivityMarksWhatsAppService::class)->createMarksCampaign(
            $staff,
            $template,
            $result['test_key'],
            'Half Yearly',
            '2026-03-20',
        );

        $params = app(WhatsAppTemplateParamResolver::class)->resolveAll(
            $template->paramSources(),
            $student->fresh(),
            $staff,
            null,
            $campaign,
        );

        $this->assertSame('Half Yearly', $params[2]);
        $this->assertStringContainsString('Maths: 45/50', $params[3]);
    }

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createEnrolledStudent(User $staff, string $rollNumber, string $mobile): Student
    {
        $student = Student::query()->create([
            'name' => 'Marks Student '.$rollNumber,
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => $mobile,
            'status' => StudentStatus::Enquiry,
            'portal_password' => app(\App\Services\StudentAuthService::class)->hashPortalPassword('15052000'),
        ]);

        $course = Course::query()->firstOr(function () {
            return Course::query()->create([
                'name' => 'Class 12 Science',
                'code' => 'DIP-MARKS',
                'programme_category' => 'coaching',
                'duration' => 6,
                'duration_type' => 'months',
                'fee' => 50000,
                'status' => CourseStatus::Active,
            ]);
        });

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

        Enrollment::query()
            ->where('student_id', $student->id)
            ->where('is_active', true)
            ->update(['enrollment_number' => $rollNumber]);

        return $student->fresh();
    }

    public function test_motion_style_preview_uses_per_subject_max_marks(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createEnrolledStudent($staff, '26175000061', '9876543601');
        $course = Course::query()->firstOrFail();

        $batch = Batch::query()->create([
            'name' => '26A1AG',
            'course_id' => $course->id,
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);

        app(BatchService::class)->assign($student, $batch, $staff);

        $headers = ['S.No.', 'Roll No', 'Batch', 'Name', 'P', 'C', 'M', 'Mark Obtain', 'Percent'];
        $rows = [[1, '26175000061', '26A1AG', 'AARAV BINDAL', 76, 57, 48, 181, 60.33]];
        $mapping = ['roll_column' => 1, 'subject_columns' => [4, 5, 6]];
        $subjectMaxMarks = [4 => 100, 5 => 100, 6 => 100];

        $preview = app(ActivityMarksBulkImportService::class)->buildPreview(
            $headers,
            $rows,
            $mapping,
            null,
            null,
            100,
            $subjectMaxMarks,
        );

        $this->assertSame(1, $preview['ready_count']);
        $this->assertSame(['Physics', 'Chemistry', 'Mathematics'], $preview['subjects']);
        $this->assertSame(100.0, $preview['subject_max_marks']['Physics']);
        $this->assertSame(76.0, $preview['rows'][0]['subject_marks']['Physics']);
    }
}
