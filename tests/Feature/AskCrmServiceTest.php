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
use App\Filament\Pages\AskCrmPage;
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
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AskCrmServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_today_reply_for_present_student(): void
    {
        $admin = $this->createSuperAdmin();
        [$student, $batch] = $this->createEnrolledStudent('Ayyush Sharma');

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'attendance_date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
            'checked_in_at' => now()->setTime(9, 12),
            'marked_by_user_id' => $admin->id,
        ]);

        $result = app(AskCrmService::class)->ask($admin, 'What is Ayyush attendance today?');

        $this->assertSame(AskCrmIntent::AttendanceToday->value, $result['intent']);
        $this->assertSame($student->id, $result['student_id']);
        $this->assertStringContainsString('Present', $result['reply']);
        $this->assertStringContainsString('Ayyush', $result['reply']);
    }

    public function test_fee_pending_reply(): void
    {
        $admin = $this->createSuperAdmin();
        [$student] = $this->createEnrolledStudent('Ayyush Sharma', withFees: true);

        $result = app(AskCrmService::class)->ask($admin, 'How much fee pending for Ayyush?');

        $this->assertSame(AskCrmIntent::FeePending->value, $result['intent']);
        $this->assertStringContainsString('2,500.00', $result['reply']);
    }

    public function test_homework_not_done_this_week_reply(): void
    {
        $admin = $this->createSuperAdmin();
        [$student, $batch] = $this->createEnrolledStudent('Ayyush Sharma');
        $subject = CourseSubject::query()->create([
            'course_id' => $batch->course_id,
            'name' => 'Math',
            'code' => 'M',
            'default_max_marks' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        HomeworkCheck::query()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'subject_name' => 'Math',
            'topic' => 'Worksheet',
            'checked_on' => now()->toDateString(),
            'status' => HomeworkCheckStatus::NotDone,
            'notify_status' => HomeworkCheckNotifyStatus::Failed,
            'created_by_user_id' => $admin->id,
        ]);

        $result = app(AskCrmService::class)->ask($admin, 'Homework not done for Ayyush this week?');

        $this->assertSame(AskCrmIntent::HomeworkWeek->value, $result['intent']);
        $this->assertStringContainsString('1 Not Done', $result['reply']);
    }

    public function test_ask_crm_page_is_accessible(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        Livewire::test(AskCrmPage::class)
            ->assertSuccessful()
            ->assertSee('bottom-right');
    }

    public function test_ask_crm_floating_widget_answers_questions(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);
        [$student, $batch] = $this->createEnrolledStudent('Ayyush Sharma');

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'attendance_date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
            'checked_in_at' => now()->setTime(9, 12),
            'marked_by_user_id' => $admin->id,
        ]);

        Livewire::test(\App\Livewire\AskCrmChatWidget::class)
            ->call('toggle')
            ->assertSet('open', true)
            ->set('message', 'What is Ayyush attendance today?')
            ->call('send')
            ->assertSee('Present');
    }

    /**
     * @return array{0: Student, 1: Batch}
     */
    private function createEnrolledStudent(string $name, bool $withFees = false): array
    {
        $staff = User::factory()->create(['is_active' => true]);

        $session = AcademicSession::query()->create([
            'name' => '2026-27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'ASK-10',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        $batch = Batch::query()->create([
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'name' => '10-A',
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
            'mobile' => '9876500011',
            'status' => StudentStatus::Enrolled,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-ASK',
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'CRM-ADM-ASK',
            'status' => AdmissionStatus::Approved,
        ]);

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'enrollment_number' => 'ROLL-ASK-01',
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

        return [$student->fresh(['activeBatchStudent.batch', 'activeEnrollment.feeStructure']), $batch];
    }

    private function createSuperAdmin(): User
    {
        Role::findOrCreate(RoleName::SuperAdmin->value);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }
}
