<?php

namespace Tests\Feature;

use App\Enums\BatchStaffRole;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\DurationType;
use App\Enums\ExamWindowStatus;
use App\Enums\ProgrammeCategory;
use App\Enums\RoleName;
use App\Models\AcademicSession;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\Course;
use App\Models\ExamWindow;
use App\Models\User;
use App\Services\ActivityAttendanceService;
use App\Services\CourseSubjectService;
use App\Services\ExamWindowService;
use App\Services\ResultDeclarationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExamWindowServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\ActivityTypeSeeder::class);
        Role::findOrCreate(RoleName::Staff->value);
    }

    public function test_create_provisions_subject_sessions_from_course(): void
    {
        [$admin, $batch] = $this->seedBatchWithSubjects();
        $examType = ActivityType::query()->where('slug', 'exam')->firstOrFail();

        $window = app(ExamWindowService::class)->create([
            'batch_id' => $batch->id,
            'activity_type_id' => $examType->id,
            'test_name' => 'Unit Test 1',
            'session_date' => '2026-07-15',
            'open_immediately' => true,
        ], $admin);

        $this->assertSame(ExamWindowStatus::Open, $window->status);
        $this->assertDatabaseCount('exam_window_subjects', 2);
        $this->assertDatabaseCount('activity_sessions', 2);

        $session = ActivitySession::query()->where('batch_id', $batch->id)->firstOrFail();
        $this->assertSame('Unit Test 1', $session->metadataValue('test_name'));
        $this->assertNotNull($session->metadataValue('course_subject_id'));
    }

    public function test_workflow_submit_approve_and_publish_gate(): void
    {
        [$admin, $batch, $lead, $subjectTeacher, $student] = $this->seedBatchWithStaffAndStudent();
        $examType = ActivityType::query()->where('slug', 'exam')->firstOrFail();
        $service = app(ExamWindowService::class);

        $window = $service->create([
            'batch_id' => $batch->id,
            'activity_type_id' => $examType->id,
            'test_name' => 'Half Yearly',
            'session_date' => '2026-09-01',
            'open_immediately' => true,
        ], $admin);

        $windowSubject = $window->subjects->first();
        $session = ActivitySession::query()->findOrFail($windowSubject->activity_session_id);

        app(ActivityAttendanceService::class)->saveMarks(
            $session,
            [$student->id => true],
            $subjectTeacher,
            [$student->id => ['marks_obtained' => 85]],
        );

        $windowSubject->refresh();
        $this->assertNotNull($windowSubject->marks_entered_at);

        $service->submit($window->fresh(), $lead);
        $this->assertSame(ExamWindowStatus::Submitted, $window->fresh()->status);

        try {
            app(ResultDeclarationService::class)->publish($window->test_key, $admin);
            $this->fail('Publish should be blocked before exam approval.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Approve', collect($exception->errors())->flatten()->first() ?? '');
        }

        $service->approve($window->fresh(), $admin);
        $this->assertSame(ExamWindowStatus::Approved, $window->fresh()->status);

        $declaration = app(ResultDeclarationService::class)->publish($window->test_key, $admin);
        $this->assertTrue($declaration->isPublished());
    }

    public function test_create_rejects_batch_without_subjects(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $course = Course::query()->create([
            'name' => 'Class 9',
            'code' => 'CLS-9',
            'programme_category' => ProgrammeCategory::School,
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);
        $session = $this->createAcademicSession();
        $batch = Batch::query()->create([
            'name' => 'Class 9-A',
            'section' => 'A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'status' => BatchStatus::Active,
        ]);
        $examType = ActivityType::query()->where('slug', 'exam')->firstOrFail();

        $this->expectException(ValidationException::class);

        app(ExamWindowService::class)->create([
            'batch_id' => $batch->id,
            'activity_type_id' => $examType->id,
            'test_name' => 'Unit Test 1',
            'session_date' => '2026-07-15',
        ], $admin);
    }

    /**
     * @return array{0: User, 1: Batch}
     */
    protected function seedBatchWithSubjects(): array
    {
        $admin = User::factory()->create(['is_active' => true]);
        Role::findOrCreate(RoleName::SuperAdmin->value);
        $admin->assignRole(RoleName::SuperAdmin->value);
        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'CLS-10',
            'programme_category' => ProgrammeCategory::School,
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'fee' => 80000,
            'status' => CourseStatus::Active,
        ]);

        app(CourseSubjectService::class)->sync($course, [
            ['name' => 'Mathematics', 'default_max_marks' => 100],
            ['name' => 'Science', 'default_max_marks' => 100],
        ]);

        $session = $this->createAcademicSession();
        $batch = Batch::query()->create([
            'name' => 'Class 10-A',
            'section' => 'A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'status' => BatchStatus::Active,
        ]);

        return [$admin, $batch];
    }

    /**
     * @return array{0: User, 1: Batch, 2: User, 3: User, 4: \App\Models\Student}
     */
    protected function seedBatchWithStaffAndStudent(): array
    {
        [$admin, $batch] = $this->seedBatchWithSubjects();
        $course = Course::query()->findOrFail($batch->course_id);
        $subjects = $course->subjects()->ordered()->get();

        $lead = User::factory()->create(['is_active' => true]);
        $lead->assignRole(RoleName::Staff->value);
        $subjectTeacher = User::factory()->create(['is_active' => true]);
        $subjectTeacher->assignRole(RoleName::Staff->value);

        BatchStaffAssignment::query()->create([
            'batch_id' => $batch->id,
            'user_id' => $lead->id,
            'role' => BatchStaffRole::LeadTeacher,
        ]);

        BatchStaffAssignment::query()->create([
            'batch_id' => $batch->id,
            'user_id' => $subjectTeacher->id,
            'role' => BatchStaffRole::SubjectTeacher,
            'course_subject_id' => $subjects->first()->id,
        ]);

        $student = $this->createEnrolledStudentForBatch($batch, $admin);

        return [$admin, $batch, $lead, $subjectTeacher, $student];
    }

    protected function createAcademicSession(): AcademicSession
    {
        return AcademicSession::query()->create([
            'name' => '2025–26',
            'code' => '2025-26',
            'starts_on' => '2025-04-01',
            'ends_on' => '2026-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);
    }

    protected function createEnrolledStudentForBatch(Batch $batch, User $staff): \App\Models\Student
    {
        $student = \App\Models\Student::query()->create([
            'name' => 'Exam Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-05-15',
            'gender' => \App\Enums\Gender::Male,
            'mobile' => '9876500101',
            'status' => \App\Enums\StudentStatus::Enrolled,
        ]);

        $enquiry = \App\Models\Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'ENQ-EXAM-1',
            'course_id' => $batch->course_id,
            'lead_source' => \App\Enums\LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $admission = \App\Models\Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-EXAM-1',
            'status' => \App\Enums\AdmissionStatus::Approved,
        ]);

        \App\Models\Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $batch->course_id,
            'academic_session_id' => $batch->academic_session_id,
            'enrollment_number' => 'ROLL-1001',
            'enrolled_at' => now(),
            'status' => \App\Enums\EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        \App\Models\BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $staff->id,
        ]);

        return $student;
    }
}
