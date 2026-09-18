<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\ResultDeclarationStatus;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Models\ActivityAttendance;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Admission;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\ResultDeclaration;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use App\Services\ActivityAttendanceService;
use App\Services\ExamTestGroupService;
use App\Services\StudentCounterService;
use App\Support\StudentExamMarksMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
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
        $this->assertSame('class-test', $absentMatrix['rows'][0]['group_key']);
    }

    public function test_profile_exam_tile_counts_appeared_tests_not_subject_papers(): void
    {
        [$staff, $batch, $type] = $this->examContext();
        $student = $this->createBatchStudent($batch, $staff, '9876500091', 'Tile Student');

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-EX-TILE',
            'course_id' => $batch->course_id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'CRM-ADM-EX-TILE',
            'status' => AdmissionStatus::Approved,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $batch->course_id,
            'enrollment_number' => 'ROLL-EX-TILE',
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        foreach (['Chemistry', 'Maths', 'Physics'] as $subject) {
            $session = $this->createTestSession($type, $batch, $staff, 'test-one', 'Test One', $subject);
            ActivityAttendance::query()->create([
                'attendable_type' => $session->getMorphClass(),
                'attendable_id' => $session->id,
                'student_id' => $student->id,
                'is_present' => true,
                'marks_obtained' => 50,
                'marked_by_user_id' => $staff->id,
            ]);
        }

        foreach (['Chemistry', 'Maths', 'Physics'] as $subject) {
            $session = $this->createTestSession($type, $batch, $staff, 'test-two', 'Test Two', $subject);
            ActivityAttendance::query()->create([
                'attendable_type' => $session->getMorphClass(),
                'attendable_id' => $session->id,
                'student_id' => $student->id,
                'is_present' => true,
                'marks_obtained' => 60,
                'marked_by_user_id' => $staff->id,
            ]);
        }

        $this->createTestSession($type, $batch, $staff, 'test-three', 'Test Three', 'Physics');

        $student = $student->fresh(['activeBatchStudent', 'activeEnrollment']);

        $this->assertSame(2, StudentExamMarksMatrix::appearedTestCountForStudent($student, $type->id));
        $this->assertSame(6, app(ActivityAttendanceService::class)->presentCountForStudent($student, $type));
        $this->assertSame(
            2,
            collect(app(StudentCounterService::class)->profile($student)['items'])->firstWhere('label', 'Exam')['value'] ?? null,
        );
    }

    public function test_profile_writer_saves_one_student_without_changing_classmates(): void
    {
        [$staff, $batch, $type] = $this->examContext();
        $session = $this->createTestSession($type, $batch, $staff, 'class-test', 'Class Test', 'Physics', 180);

        $appeared = $this->createBatchStudent($batch, $staff, '9876500011', 'Appeared Student');
        $absent = $this->createBatchStudent($batch, $staff, '9876500012', 'Late Student');

        ActivityAttendance::query()->create([
            'attendable_type' => $session->getMorphClass(),
            'attendable_id' => $session->id,
            'student_id' => $appeared->id,
            'is_present' => true,
            'marks_obtained' => 77,
            'marked_by_user_id' => $staff->id,
        ]);

        $saved = app(\App\Services\StudentExamMarksWriter::class)->saveForStudent(
            $absent->fresh(),
            'class-test',
            ['Physics' => 91],
            $staff,
        );

        $this->assertGreaterThan(0, $saved);
        $this->assertDatabaseHas('activity_attendances', [
            'attendable_id' => $session->id,
            'student_id' => $absent->id,
            'marks_obtained' => 91,
        ]);
        $this->assertDatabaseHas('activity_attendances', [
            'attendable_id' => $session->id,
            'student_id' => $appeared->id,
            'marks_obtained' => 77,
        ]);
    }

    public function test_class_grid_writer_updates_many_students_on_the_same_exam(): void
    {
        [$staff, $batch, $type] = $this->examContext();
        $physics = $this->createTestSession($type, $batch, $staff, 'grid-test', 'Grid Test', 'Physics', 100);
        $maths = $this->createTestSession($type, $batch, $staff, 'grid-test', 'Grid Test', 'Maths', 100);
        $keep = $this->createBatchStudent($batch, $staff, '9876500081', 'Keep Student');
        $change = $this->createBatchStudent($batch, $staff, '9876500082', 'Change Student');

        foreach ([$keep, $change] as $student) {
            app(ActivityAttendanceService::class)->saveMarks(
                $physics,
                [$student->id => true],
                $staff,
                [$student->id => ['marks_obtained' => 40]],
            );
        }

        $saved = app(\App\Services\StudentExamMarksWriter::class)->saveForGroup(
            'grid-test',
            [
                $keep->id => ['Physics' => 40, 'Maths' => ''],
                $change->id => ['Physics' => 91, 'Maths' => 88],
            ],
            $staff,
        );

        $this->assertGreaterThan(0, $saved);
        $this->assertDatabaseHas('activity_attendances', [
            'attendable_id' => $physics->id,
            'student_id' => $keep->id,
            'marks_obtained' => 40,
        ]);
        $this->assertDatabaseHas('activity_attendances', [
            'attendable_id' => $physics->id,
            'student_id' => $change->id,
            'marks_obtained' => 91,
        ]);
        $this->assertDatabaseHas('activity_attendances', [
            'attendable_id' => $maths->id,
            'student_id' => $change->id,
            'marks_obtained' => 88,
        ]);
        $this->assertDatabaseHas('activity_attendances', [
            'attendable_id' => $maths->id,
            'student_id' => $keep->id,
            'is_present' => 0,
            'marks_obtained' => null,
        ]);
    }

    public function test_student_profile_keeps_maths_marks_when_a_twin_empty_sheet_exists(): void
    {
        [$staff, $batch, $type] = $this->examContext();
        $student = $this->createBatchStudent($batch, $staff, '9876500003', 'Rishit Gupta');

        $mathematics = $this->createTestSession($type, $batch, $staff, 'jee-a-test', '11TH JEE BATCH (A) TEST', 'Mathematics', 100);
        $this->createTestSession($type, $batch, $staff, 'jee-a-test', '11TH JEE BATCH (A) TEST', 'Maths', 100);
        $chemistry = $this->createTestSession($type, $batch, $staff, 'jee-a-test', '11TH JEE BATCH (A) TEST', 'Chemistry', 100);
        $physics = $this->createTestSession($type, $batch, $staff, 'jee-a-test', '11TH JEE BATCH (A) TEST', 'Physics', 100);

        foreach ([
            [$mathematics, 48],
            [$chemistry, 76],
            [$physics, 65],
        ] as [$session, $marks]) {
            ActivityAttendance::query()->create([
                'attendable_type' => $session->getMorphClass(),
                'attendable_id' => $session->id,
                'student_id' => $student->id,
                'is_present' => true,
                'marks_obtained' => $marks,
                'marked_by_user_id' => $staff->id,
            ]);
        }

        $row = StudentExamMarksMatrix::forStudent($student->fresh(), $type->id)['rows'][0];

        $this->assertSame(48.0, $row['scores']['Maths']['marks']);
        $this->assertSame(76.0, $row['scores']['Chemistry']['marks']);
        $this->assertSame(65.0, $row['scores']['Physics']['marks']);
        $this->assertSame(189.0, $row['total']['marks']);
        $this->assertSame(300.0, $row['total']['max']);
        $this->assertSame(63.0, $row['total']['percentage']);
        $this->assertArrayNotHasKey('Mathematics', $row['scores']);
    }

    public function test_totals_include_negative_marks_in_weighted_percentage(): void
    {
        [$staff, $batch, $type] = $this->examContext();
        $student = $this->createBatchStudent($batch, $staff, '9876500004', 'Neg Marks Student');

        $maths = $this->createTestSession($type, $batch, $staff, 'neg-test', 'Negative Test', 'Maths', 100);
        $physics = $this->createTestSession($type, $batch, $staff, 'neg-test', 'Negative Test', 'Physics', 100);
        $chemistry = $this->createTestSession($type, $batch, $staff, 'neg-test', 'Negative Test', 'Chemistry', 100);
        $attendance = app(ActivityAttendanceService::class);

        $attendance->saveMarks($maths, [$student->id => true], $staff, [$student->id => ['marks_obtained' => -10]]);
        $attendance->saveMarks($physics, [$student->id => true], $staff, [$student->id => ['marks_obtained' => 80]]);
        $attendance->saveMarks($chemistry, [$student->id => true], $staff, [$student->id => ['marks_obtained' => 40]]);

        $row = StudentExamMarksMatrix::forStudent($student->fresh(), $type->id)['rows'][0];

        $this->assertSame(-10.0, $row['scores']['Maths']['marks']);
        $this->assertSame(110.0, $row['total']['marks']);
        $this->assertSame(300.0, $row['total']['max']);
        $this->assertSame(36.67, $row['total']['percentage']);
        $this->assertStringContainsString('-10', $row['scores']['Maths']['display']);
    }

    public function test_rename_keeps_test_key_and_marks(): void
    {
        [$staff, $batch, $type] = $this->examContext();
        $student = $this->createBatchStudent($batch, $staff, '9876500099', 'Rename Student');
        $maths = $this->createTestSession($type, $batch, $staff, 'rename-key', 'Old Display Name', 'Maths');
        $this->createTestSession($type, $batch, $staff, 'rename-key', 'Old Display Name', 'Physics');

        app(ActivityAttendanceService::class)->saveMarks(
            $maths,
            [$student->id => true],
            $staff,
            [$student->id => ['marks_obtained' => 72]],
        );

        app(ExamTestGroupService::class)->renameGroup($staff, 'rename-key', 'New Display Name');

        $maths->refresh();
        $physics = ActivitySession::query()
            ->where('metadata->test_key', 'rename-key')
            ->where('metadata->subject', 'Physics')
            ->firstOrFail();

        $this->assertSame('rename-key', $maths->metadataValue('test_key'));
        $this->assertSame('New Display Name', $maths->metadataValue('test_name'));
        $this->assertSame('New Display Name', $physics->metadataValue('test_name'));
        $this->assertSame('rename-key', $physics->metadataValue('test_key'));
        $this->assertStringStartsWith('New Display Name —', (string) $maths->title);
        $this->assertDatabaseHas('activity_attendances', [
            'attendable_id' => $maths->id,
            'student_id' => $student->id,
            'marks_obtained' => 72,
        ]);
    }

    public function test_marks_cannot_go_below_negative_of_max(): void
    {
        [$staff, $batch, $type] = $this->examContext();
        $student = $this->createBatchStudent($batch, $staff, '9876500005', 'Below Floor Student');
        $maths = $this->createTestSession($type, $batch, $staff, 'floor-test', 'Floor Test', 'Maths', 100);

        $this->expectException(ValidationException::class);

        app(ActivityAttendanceService::class)->saveMarks(
            $maths,
            [$student->id => true],
            $staff,
            [$student->id => ['marks_obtained' => -100.01]],
        );
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
