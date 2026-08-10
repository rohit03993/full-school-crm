<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\AskCrmIntent;
use App\Enums\AttendanceStatus;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\HomeworkCheckNotifyStatus;
use App\Enums\HomeworkCheckStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\CourseSubject;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\HomeworkCheck;
use App\Models\Student;
use App\Models\User;
use App\Services\AskCrmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Real-world staff questions — regression guard for Ask CRM quality.
 */
class AskCrmGoldenQuestionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Student $student;

    protected Batch $batch;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ask_crm.use_ai' => false]);
        $this->travelTo('2026-08-10 10:00:00');

        $this->admin = $this->createSuperAdmin();
        [$this->student, $this->batch] = $this->createEnrolledStudent('Abhinav Singh', withFees: true);

        $subject = CourseSubject::query()->create([
            'course_id' => $this->batch->course_id,
            'name' => 'Maths',
            'code' => 'M01',
            'default_max_marks' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        Attendance::query()->create([
            'batch_id' => $this->batch->id,
            'student_id' => $this->student->id,
            'attendance_date' => '2026-08-10',
            'status' => AttendanceStatus::Present,
            'checked_in_at' => now()->setTime(9, 5),
            'marked_by_user_id' => $this->admin->id,
        ]);

        HomeworkCheck::query()->create([
            'student_id' => $this->student->id,
            'batch_id' => $this->batch->id,
            'course_subject_id' => $subject->id,
            'subject_name' => 'Maths',
            'topic' => "Today's homework",
            'checked_on' => '2026-08-09',
            'status' => HomeworkCheckStatus::NotDone,
            'notify_status' => HomeworkCheckNotifyStatus::Failed,
            'created_by_user_id' => $this->admin->id,
        ]);
    }

    /**
     * @return array<string, array{0: string, 1: list<string>, 2: ?string}>
     */
    public static function goldenQuestionsProvider(): array
    {
        return [
            'name dash homework date' => [
                'ABHINAV SINGH - homework for 9 aug 2026',
                ['Not Done', '09 Aug 2026', 'Maths'],
                AskCrmIntent::HomeworkWeek->value,
            ],
            'homework status general' => [
                'ABHINAV SINGH — I need homework status',
                ['Not Done', '09 Aug 2026'],
                AskCrmIntent::HomeworkWeek->value,
            ],
            'attendance today' => [
                'What is Abhinav Singh attendance today?',
                ['Present', 'Abhinav'],
                AskCrmIntent::AttendanceToday->value,
            ],
            'fee pending' => [
                'How much fee pending for Abhinav Singh?',
                ['2,500.00', 'Abhinav'],
                AskCrmIntent::FeePending->value,
            ],
            'date follow up' => [
                'what about 9 aug 2026',
                ['Not Done', '09 Aug 2026'],
                null,
            ],
            'this student follow up' => [
                'and cases open for this student',
                ['case', 'Abhinav'],
                null,
            ],
            'full name before homework status' => [
                'ABHINAV SINGH what is the homework status',
                ['Not Done', 'Abhinav'],
                AskCrmIntent::HomeworkWeek->value,
            ],
        ];
    }

    /**
     * @param  list<string>  $mustContain
     *
     * @dataProvider goldenQuestionsProvider
     */
    public function test_golden_staff_questions(string $question, array $mustContain, ?string $expectedIntent): void
    {
        $service = app(AskCrmService::class);

        $history = [];
        $contextId = null;

        if ($question === 'what about 9 aug 2026') {
            $first = $service->ask($this->admin, 'homework for abhinav singh');
            $contextId = (int) $this->student->id;
            $history = [
                ['role' => 'user', 'text' => 'homework for abhinav singh'],
                ['role' => 'assistant', 'text' => $first['reply']],
            ];
        }

        if ($question === 'and cases open for this student') {
            $first = $service->ask($this->admin, 'ABHINAV SINGH -- fee pending');
            $contextId = (int) $this->student->id;
            $history = [
                ['role' => 'user', 'text' => 'ABHINAV SINGH -- fee pending'],
                ['role' => 'assistant', 'text' => $first['reply']],
            ];
        }

        $result = $service->ask($this->admin, $question, $history, $contextId);

        $this->assertSame($this->student->id, $result['student_id'], $result['reply']);

        foreach ($mustContain as $fragment) {
            $this->assertStringContainsString($fragment, $result['reply'], 'Question: '.$question);
        }

        if ($expectedIntent !== null) {
            $this->assertSame($expectedIntent, $result['intent'], $result['reply']);
        }
    }

    private function createSuperAdmin(): User
    {
        $role = Role::firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }

    /**
     * @return array{0: Student, 1: Batch}
     */
    private function createEnrolledStudent(string $name, bool $withFees = false): array
    {
        $staff = User::factory()->create(['is_active' => true]);

        $session = AcademicSession::query()->create([
            'name' => '2026-27',
            'code' => '2026-27-G',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 11',
            'code' => 'ASK-11-'.substr(md5($name), 0, 6),
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        $batch = Batch::query()->create([
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'name' => '11-JEE',
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);

        $student = Student::query()->create([
            'name' => $name,
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '98765'.random_int(10000, 99999),
            'status' => StudentStatus::Enrolled,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'GQ-ENQ-'.substr(md5($name), 0, 8),
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'GQ-ADM-'.substr(md5($name), 0, 8),
            'status' => AdmissionStatus::Approved,
        ]);

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'enrollment_number' => 'GQ-'.substr(md5($name), 0, 8),
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        if ($withFees) {
            FeeStructure::query()->create([
                'enrollment_id' => $enrollment->id,
                'course_fee' => 10000,
                'discount_amount' => 0,
                'net_fee' => 10000,
                'paid_amount' => 7500,
                'pending_amount' => 2500,
            ]);
        }

        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $staff->id,
        ]);

        return [$student, $batch];
    }
}
