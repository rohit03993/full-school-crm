<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\ResultDeclarationStatus;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\ActivityAttendance;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\ResultDeclaration;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use App\Services\ExamTestGroupService;
use App\Support\StudentExamMarksMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ExamTestGroupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_delete_removes_only_that_exam_when_parents_were_not_messaged(): void
    {
        [$staff, $batch, $type] = $this->examContext();
        $keep = $this->createTestSession($type, $batch, $staff, 'keep-test', 'Keep Test', 'Physics');
        $drop = $this->createTestSession($type, $batch, $staff, 'drop-test', 'Drop Test', 'Physics');

        $deleted = app(ExamTestGroupService::class)->deleteGroup($staff, 'drop-test');

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('activity_sessions', ['id' => $drop->id]);
        $this->assertDatabaseHas('activity_sessions', ['id' => $keep->id]);
    }

    public function test_delete_is_blocked_after_whatsapp_to_parents(): void
    {
        [$staff, $batch, $type] = $this->examContext();
        $session = $this->createTestSession($type, $batch, $staff, 'wa-test', 'WA Test', 'Physics');

        $template = WhatsAppTemplate::query()->create([
            'name' => 'marks',
            'param_count' => 0,
            'body' => 'Hello',
            'is_active' => true,
        ]);

        WhatsAppCampaign::query()->create([
            'whatsapp_template_id' => $template->id,
            'name' => 'Marks · WA Test',
            'status' => WhatsAppCampaignStatus::Completed,
            'total_recipients' => 1,
            'sent_count' => 1,
            'failed_count' => 0,
            'campaign_variables' => [
                'audience_source' => 'activity_marks',
                'test_key' => 'wa-test',
            ],
        ]);

        $eligibility = app(ExamTestGroupService::class)->deleteEligibility(['wa-test']);

        $this->assertFalse($eligibility['wa-test']['allowed']);

        try {
            app(ExamTestGroupService::class)->deleteGroup($staff, 'wa-test');
            $this->fail('Expected delete to be blocked.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertNotEmpty($exception->errors());
        }

        $this->assertDatabaseHas('activity_sessions', ['id' => $session->id]);
    }

    public function test_delete_is_blocked_when_results_are_published(): void
    {
        [$staff, $batch, $type] = $this->examContext();
        $session = $this->createTestSession($type, $batch, $staff, 'pub-test', 'Pub Test', 'Physics');

        ResultDeclaration::query()->create([
            'group_key' => 'pub-test',
            'test_name' => 'Pub Test',
            'session_date' => now()->toDateString(),
            'batch_id' => $batch->id,
            'activity_type_id' => $type->id,
            'status' => ResultDeclarationStatus::Published,
            'declared_at' => now(),
        ]);

        $eligibility = app(ExamTestGroupService::class)->deleteEligibility(['pub-test']);

        $this->assertFalse($eligibility['pub-test']['allowed']);
        $this->assertDatabaseHas('activity_sessions', ['id' => $session->id]);
    }

    public function test_class_exam_shows_on_student_profile_with_blank_marks_if_absent(): void
    {
        [$staff, $batch, $type] = $this->examContext();
        $session = $this->createTestSession($type, $batch, $staff, 'class-test', 'Class Test', 'Physics', 180);

        $appeared = $this->createBatchStudent($batch, $staff, '9876500001', 'Appeared Student');
        $absent = $this->createBatchStudent($batch, $staff, '9876500002', 'Absent Student');

        ActivityAttendance::query()->create([
            'attendable_type' => $session->getMorphClass(),
            'attendable_id' => $session->id,
            'student_id' => $appeared->id,
            'is_present' => true,
            'marks_obtained' => 77,
            'marked_by_user_id' => $staff->id,
        ]);

        $appearedMatrix = StudentExamMarksMatrix::forStudent($appeared->fresh(), $type->id);
        $absentMatrix = StudentExamMarksMatrix::forStudent($absent->fresh(), $type->id);

        $this->assertCount(1, $appearedMatrix['rows']);
        $this->assertCount(1, $absentMatrix['rows']);
        $this->assertSame('Class Test', $absentMatrix['rows'][0]['label']);
        $this->assertTrue($appearedMatrix['rows'][0]['appeared']);
        $this->assertFalse($absentMatrix['rows'][0]['appeared']);
        $this->assertSame(77.0, $appearedMatrix['rows'][0]['scores']['Physics']['marks']);
        $this->assertNull($absentMatrix['rows'][0]['scores']['Physics']['marks']);
        $this->assertSame(180.0, $absentMatrix['rows'][0]['scores']['Physics']['max']);
        $this->assertSame('', $absentMatrix['rows'][0]['scores']['Physics']['display']);
    }

    /**
     * @return array{0: User, 1: Batch, 2: ActivityType}
     */
    protected function examContext(): array
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole(RoleName::SuperAdmin->value);

        $course = Course::query()->create([
            'name' => 'Class 11',
            'code' => 'C11-EX',
            'programme_category' => 'coaching',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 1000,
            'status' => CourseStatus::Active,
        ]);
        $batch = Batch::query()->create([
            'name' => 'Class 11-A',
            'course_id' => $course->id,
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);
        $type = ActivityType::query()->create([
            'name' => 'Exam',
            'field_schema' => [
                ['key' => 'subject', 'label' => 'Subject', 'type' => 'text'],
                ['key' => 'max_marks', 'label' => 'Max Marks', 'type' => 'number'],
            ],
            'is_enabled' => true,
        ]);

        return [$staff, $batch, $type];
    }

    protected function createTestSession(
        ActivityType $type,
        Batch $batch,
        User $staff,
        string $testKey,
        string $testName,
        string $subject,
        float $maxMarks = 100,
    ): ActivitySession {
        return ActivitySession::query()->create([
            'activity_type_id' => $type->id,
            'title' => "{$testName} — {$subject}",
            'session_date' => now()->toDateString(),
            'batch_id' => $batch->id,
            'metadata' => [
                'test_key' => $testKey,
                'test_name' => $testName,
                'subject' => $subject,
                'max_marks' => $maxMarks,
            ],
            'created_by_user_id' => $staff->id,
        ]);
    }

    protected function createBatchStudent(Batch $batch, User $staff, string $mobile, string $name): Student
    {
        $student = Student::query()->create([
            'name' => $name,
            'father_name' => 'Parent',
            'date_of_birth' => '2008-05-15',
            'gender' => Gender::Male,
            'mobile' => $mobile,
            'status' => StudentStatus::Enrolled,
            'portal_password' => app(\App\Services\StudentAuthService::class)->hashPortalPassword('15052008'),
        ]);

        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'assigned_at' => now(),
            'is_active' => true,
            'assigned_by_user_id' => $staff->id,
        ]);

        return $student;
    }
}
