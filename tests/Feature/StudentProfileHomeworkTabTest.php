<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\HomeworkCheckNotifyStatus;
use App\Enums\HomeworkCheckStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Pages\StudentProfilePage;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\CourseSubject;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\HomeworkCheck;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentProfileHomeworkTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_homework_tab_shows_not_done_check_marks(): void
    {
        $admin = $this->createSuperAdmin();
        [$student, $batch, $subject] = $this->createEnrolledStudent();

        HomeworkCheck::query()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'subject_name' => 'Mathematics',
            'topic' => "Today's homework",
            'checked_on' => now()->toDateString(),
            'status' => HomeworkCheckStatus::NotDone,
            'parent_mobile' => $student->mobile,
            'notify_status' => HomeworkCheckNotifyStatus::Sent,
            'notified_at' => now(),
            'created_by_user_id' => $admin->id,
        ]);

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->set('profileTab', 'homework')
            ->assertSet('profileTab', 'homework')
            ->assertSet('homeworkTabLoaded', true)
            ->assertStatus(200)
            ->assertSee('Homework check marks')
            ->assertSee('Not Done')
            ->assertSee('Mathematics')
            ->assertSee("Today's homework");
    }

    /**
     * @return array{0: Student, 1: Batch, 2: CourseSubject}
     */
    private function createEnrolledStudent(): array
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
            'code' => 'HW-10',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        $subject = CourseSubject::query()->create([
            'course_id' => $course->id,
            'name' => 'Mathematics',
            'code' => 'MATH',
            'default_max_marks' => 100,
            'sort_order' => 1,
            'is_active' => true,
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
            'name' => 'Homework Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876543211',
            'status' => StudentStatus::Enrolled,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-HW',
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'CRM-ADM-HW',
            'status' => AdmissionStatus::Approved,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'enrollment_number' => 'ROLL-HW-01',
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $staff->id,
        ]);

        return [
            $student->fresh(['activeBatchStudent', 'activeEnrollment']),
            $batch,
            $subject,
        ];
    }

    private function createSuperAdmin(): User
    {
        $role = Role::findOrCreate(RoleName::SuperAdmin->value);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
