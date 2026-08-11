<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\HomeworkContentType;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\HomeworkAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HomeworkAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_homework_and_record_view_for_batch_student(): void
    {
        [$student, $batch, $staff] = $this->createStudentInBatch();

        $assignment = app(HomeworkAssignmentService::class)->create($staff, [
            'batch_id' => $batch->id,
            'title' => 'Chapter 5 exercises',
            'description' => 'Complete all questions.',
            'send_whatsapp' => false,
        ]);

        $this->assertSame(HomeworkContentType::Text, $assignment->content_type);
        $this->assertTrue(app(HomeworkAssignmentService::class)->studentCanAccess($assignment, $student));

        app(HomeworkAssignmentService::class)->recordView($assignment, $student);

        $this->assertDatabaseHas('homework_views', [
            'homework_assignment_id' => $assignment->id,
            'student_id' => $student->id,
        ]);

        $assignments = app(HomeworkAssignmentService::class)->assignmentsForStudent($student);

        $this->assertCount(1, $assignments);
        $this->assertTrue($assignments->first()->views->isNotEmpty());
    }

    public function test_create_assigns_public_token_and_public_page_needs_no_login(): void
    {
        [, $batch, $staff] = $this->createStudentInBatch();

        $assignment = app(HomeworkAssignmentService::class)->create($staff, [
            'batch_id' => $batch->id,
            'title' => 'Public link homework',
            'description' => 'Open without portal login.',
            'send_whatsapp' => false,
        ]);

        $this->assertNotEmpty($assignment->public_token);
        $this->assertStringContainsString('/h/', $assignment->publicUrl());
        $this->assertStringNotContainsString('/portal/', $assignment->publicUrl());

        $this->get($assignment->publicUrl())
            ->assertOk()
            ->assertSee('Public link homework')
            ->assertSee('Open without portal login.');
    }

    public function test_whatsapp_notify_uses_public_homework_link(): void
    {
        [$student, $batch, $staff] = $this->createStudentInBatch();

        $assignment = app(HomeworkAssignmentService::class)->create($staff, [
            'batch_id' => $batch->id,
            'title' => 'WA share homework',
            'description' => 'Check link.',
            'send_whatsapp' => false,
        ]);

        \App\Models\MetaWhatsAppTemplate::query()->create([
            'name' => 'homework_api',
            'language' => 'en',
            'status' => 'APPROVED',
            'param_count' => 4,
            'body' => 'Hi {{1}} {{2}} {{3}} {{4}}',
            'is_active' => true,
            'synced_at' => now(),
        ]);

        $fake = \Mockery::mock(\App\Services\WhatsAppDispatchService::class)->makePartial();
        $fake->shouldReceive('isConfigured')->andReturn(true);
        $fake->shouldReceive('resolveMetaTemplatePublic')
            ->andReturn(\App\Models\MetaWhatsAppTemplate::query()->where('name', 'homework_api')->first());
        $fake->shouldReceive('send')
            ->once()
            ->withArgs(function (string $mobile, array $params) use ($student, $assignment): bool {
                return $mobile === $student->mobile
                    && $params[0] === $student->name
                    && $params[2] === 'WA share homework'
                    && str_contains($params[3], '/h/'.$assignment->public_token);
            })
            ->andReturn(['status' => 'success']);

        $this->app->instance(\App\Services\WhatsAppDispatchService::class, $fake);
        $this->app->forgetInstance(\App\Services\HomeworkWhatsAppService::class);

        $result = app(\App\Services\HomeworkWhatsAppService::class)->notifyBatch($assignment->fresh());

        $this->assertSame(1, $result['sent']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame('homework_api', $result['template']);
    }

    /**
     * @return array{0: Student, 1: Batch, 2: User}
     */
    protected function createStudentInBatch(): array
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole(RoleName::SuperAdmin->value);

        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 11',
            'code' => 'CLS-11',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $batch = Batch::query()->create([
            'name' => '11-A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        $student = Student::query()->create([
            'name' => 'Demo Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => '8320936486',
            'status' => StudentStatus::Enrolled,
            'portal_password' => app(\App\Services\StudentAuthService::class)->hashPortalPassword('15052000'),
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-2026-000501',
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'CRM-ADM-2026-000501',
            'status' => AdmissionStatus::Approved,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'enrollment_number' => 'ROLL-101',
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'assigned_at' => now(),
            'assigned_by_user_id' => $staff->id,
            'is_active' => true,
        ]);

        return [$student->fresh(), $batch, $staff];
    }
}
