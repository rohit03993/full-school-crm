<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\HomeworkAssignmentStatus;
use App\Enums\HomeworkCheckStatus;
use App\Enums\HomeworkContentType;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\StudentStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\HomeworkCheckReportWidget;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\CourseSubject;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCheck;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class HomeworkCheckReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_does_not_see_the_homework_check_report(): void
    {
        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->assignRole(StaffJobRole::Teacher->value);

        $this->actingAs($teacher);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertFalse(HomeworkCheckReportWidget::canView());

        Livewire::test(Dashboard::class)
            ->assertSuccessful()
            ->assertDontSee('Homework check');
    }

    public function test_coordinator_sees_each_teachers_counts_for_one_date(): void
    {
        Carbon::setTestNow('2026-10-05 12:00:00');

        try {
            $coordinator = User::factory()->create(['is_active' => true, 'name' => 'Academic Head']);
            $coordinator->assignRole(StaffJobRole::AcademicCoordinator->value);
            $this->actingAs($coordinator);
            Filament::setCurrentPanel(Filament::getPanel('admin'));

            [$batch, $subject, $first, $second, $rahul, $anita] = $this->seedClass();
            $this->seedEmptyClass();

            $kuldeepHomework = $this->saveHomework($batch, $subject, $first, HomeworkAssignmentStatus::Approved, '2026-10-04');
            $sunilHomework = $this->saveHomework($batch, $subject, $second, HomeworkAssignmentStatus::Approved, '2026-10-04');
            $this->saveCheck($kuldeepHomework, $rahul, HomeworkCheckStatus::Done);
            $this->saveCheck($sunilHomework, $rahul, HomeworkCheckStatus::NotDone);

            Livewire::test(HomeworkCheckReportWidget::class)
                ->assertSee('Homework check')
                ->assertSee('Every class for 4 Oct 2026.')
                ->assertSee('Class 10 · Section A')
                ->assertSee('Chemistry (Kuldeep Rana)')
                ->assertSee('Done 1')
                ->assertSee('Not done 0')
                ->assertSee('Not marked 1')
                ->assertSee('Chemistry (Sunil Rana)')
                ->assertSee('Done 0')
                ->assertSee('Not done 1')
                ->assertSee('Class 10 · Section B')
                ->assertSee('No homework')
                ->assertDontSee('Section Z');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_today_and_unapproved_homework_keep_the_counts_closed(): void
    {
        Carbon::setTestNow('2026-10-05 12:00:00');

        try {
            $admin = User::factory()->create(['is_active' => true]);
            $admin->assignRole(RoleName::SuperAdmin->value);
            $this->actingAs($admin);
            Filament::setCurrentPanel(Filament::getPanel('admin'));

            [$batch, $subject, $first] = $this->seedClass();
            $this->saveHomework($batch, $subject, $first, HomeworkAssignmentStatus::Approved, '2026-10-05');

            Livewire::test(HomeworkCheckReportWidget::class)
                ->set('reportDate', '2026-10-05')
                ->assertSee('Counts stay closed today.')
                ->assertSee('Chemistry (Kuldeep Rana)')
                ->assertSee('Check opens tomorrow')
                ->assertDontSee('Done 0');

            $waiting = $this->saveHomework($batch, $subject, $first, HomeworkAssignmentStatus::Submitted, '2026-10-03');

            Livewire::test(HomeworkCheckReportWidget::class)
                ->set('reportDate', '2026-10-03')
                ->assertSee('Waiting for approval')
                ->assertDontSee('Done 0')
                ->assertDontSee('Open');

            $this->assertNotNull($waiting->id);
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * @return array{0: Batch, 1: CourseSubject, 2: User, 3: User, 4: Student, 5: Student}
     */
    protected function seedClass(): array
    {
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
            'code' => 'CLS-10',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        $subject = CourseSubject::query()->create([
            'course_id' => $course->id,
            'name' => 'Chemistry',
            'code' => 'CHEM',
            'default_max_marks' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $batch = Batch::query()->create([
            'name' => 'Class 10 - A',
            'section' => 'A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        $batch->subjects()->attach($subject->id, ['sort_order' => 1]);

        $first = User::factory()->create(['name' => 'Kuldeep Rana', 'is_active' => true]);
        $second = User::factory()->create(['name' => 'Sunil Rana', 'is_active' => true]);

        $rahul = Student::query()->create([
            'name' => 'Rahul',
            'mobile' => '9876543210',
            'status' => StudentStatus::Enrolled,
        ]);
        $anita = Student::query()->create([
            'name' => 'Anita',
            'mobile' => '9876543211',
            'status' => StudentStatus::Enrolled,
        ]);

        foreach ([$rahul, $anita] as $student) {
            BatchStudent::query()->create([
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'is_active' => true,
                'assigned_at' => now(),
                'assigned_by_user_id' => $first->id,
            ]);
        }

        Batch::query()->create([
            'name' => 'Old section',
            'section' => 'Z',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Completed,
        ]);

        return [$batch, $subject, $first, $second, $rahul, $anita];
    }

    protected function seedEmptyClass(): void
    {
        $course = Course::query()->where('code', 'CLS-10')->firstOrFail();
        $session = AcademicSession::query()->where('code', '2026-27')->firstOrFail();

        Batch::query()->create([
            'name' => 'Class 10 - B',
            'section' => 'B',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);
    }

    protected function saveHomework(
        Batch $batch,
        CourseSubject $subject,
        User $teacher,
        HomeworkAssignmentStatus $status,
        string $date,
    ): HomeworkAssignment {
        return HomeworkAssignment::query()->create([
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'created_by_user_id' => $teacher->id,
            'submitted_by_user_id' => $teacher->id,
            'title' => 'Organic',
            'description' => 'Page 1',
            'content_type' => HomeworkContentType::Text,
            'status' => $status,
            'homework_date' => $date,
            'published_at' => now(),
        ]);
    }

    protected function saveCheck(
        HomeworkAssignment $assignment,
        Student $student,
        HomeworkCheckStatus $status,
    ): void {
        HomeworkCheck::query()->create([
            'student_id' => $student->id,
            'batch_id' => $assignment->batch_id,
            'course_subject_id' => $assignment->course_subject_id,
            'homework_assignment_id' => $assignment->id,
            'subject_name' => 'Chemistry ('.$assignment->submittedBy?->name.')',
            'topic' => 'Organic',
            'checked_on' => $assignment->homework_date,
            'status' => $status,
            'created_by_user_id' => $assignment->submitted_by_user_id,
        ]);
    }
}
