<?php

namespace Tests\Feature;

use App\Enums\BatchStaffRole;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\HomeworkAssignmentStatus;
use App\Enums\HomeworkCheckStatus;
use App\Enums\HomeworkContentType;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\StudentStatus;
use App\Filament\Pages\TeacherHomeworkReportPage;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\CourseSubject;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCheck;
use App\Models\Student;
use App\Models\User;
use App\Services\TeacherHomeworkScoreService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class TeacherHomeworkScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_sunday_counts_and_each_teacher_keeps_his_own_score(): void
    {
        Carbon::setTestNow('2026-10-05 12:00:00');

        try {
            [$batch, $subject, $kuldeep, $sunil, $rahul] = $this->seedClass();
            $homework = $this->saveHomework($batch, $subject, $kuldeep, HomeworkAssignmentStatus::Approved, '2026-10-04');
            HomeworkCheck::query()->create([
                'student_id' => $rahul->id,
                'batch_id' => $batch->id,
                'course_subject_id' => $subject->id,
                'homework_assignment_id' => $homework->id,
                'subject_name' => 'Chemistry (Kuldeep Rana)',
                'topic' => 'Organic',
                'checked_on' => '2026-10-04',
                'status' => HomeworkCheckStatus::Done,
                'created_by_user_id' => $kuldeep->id,
            ]);

            $report = app(TeacherHomeworkScoreService::class)->report('2026-10-03', '2026-10-04', null, true);
            $rows = collect($report['teachers'])->keyBy('name');

            $this->assertSame(1, $rows['Kuldeep Rana']['expected']);
            $this->assertSame(1, $rows['Kuldeep Rana']['given']);
            $this->assertSame(0, $rows['Kuldeep Rana']['missed']);
            $this->assertSame(75, $rows['Kuldeep Rana']['score']);
            $this->assertSame(1, $rows['Kuldeep Rana']['lines'][0]['done']);
            $this->assertSame(1, $rows['Kuldeep Rana']['lines'][0]['unmarked']);

            $this->assertSame(1, $rows['Sunil Rana']['expected']);
            $this->assertSame(0, $rows['Sunil Rana']['given']);
            $this->assertSame(1, $rows['Sunil Rana']['missed']);
            $this->assertSame(0, $rows['Sunil Rana']['score']);
            $this->assertSame('Missed', $rows['Sunil Rana']['lines'][0]['state']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_today_homework_counts_as_given_and_not_as_checked(): void
    {
        Carbon::setTestNow('2026-10-05 12:00:00');

        try {
            [$batch, $subject, $kuldeep] = $this->seedClass();
            $this->saveHomework($batch, $subject, $kuldeep, HomeworkAssignmentStatus::Approved, '2026-10-05');

            $report = app(TeacherHomeworkScoreService::class)->report('2026-10-05', '2026-10-05', (int) $kuldeep->id, true);
            $row = $report['teachers'][0];

            $this->assertSame(1, $row['given']);
            $this->assertSame(0, $row['ready']);
            $this->assertSame(100, $row['score']);
            $this->assertSame('Check opens tomorrow', $row['lines'][0]['note']);
            $this->assertFalse($row['parts'][1]['applicable']);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_admin_sees_every_teacher_and_a_teacher_sees_only_himself(): void
    {
        Carbon::setTestNow('2026-10-05 12:00:00');

        try {
            [$batch, $subject, $kuldeep, $sunil] = $this->seedClass();
            $this->saveHomework($batch, $subject, $kuldeep, HomeworkAssignmentStatus::Approved, '2026-10-04');

            $admin = User::factory()->create(['is_active' => true]);
            $admin->assignRole(RoleName::SuperAdmin->value);
            $this->actingAs($admin);
            Filament::setCurrentPanel(Filament::getPanel('admin'));

            Livewire::test(TeacherHomeworkReportPage::class)
                ->set('dateFrom', '2026-10-04')
                ->set('dateTo', '2026-10-04')
                ->assertSee('Kuldeep Rana')
                ->assertSee('Sunil Rana')
                ->call('openTeacher', $kuldeep->id)
                ->assertSee('Chemistry (Kuldeep Rana)')
                ->assertDontSee('Chemistry (Sunil Rana)');

            $this->actingAs($sunil);
            Filament::setCurrentPanel(Filament::getPanel('admin'));

            Livewire::test(TeacherHomeworkReportPage::class)
                ->set('dateFrom', '2026-10-04')
                ->set('dateTo', '2026-10-04')
                ->assertSee('Sunil Rana')
                ->assertDontSee('Kuldeep Rana');
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
            'code' => '2026-27-score',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'CLS-10-SCORE',
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

        $kuldeep = User::factory()->create(['name' => 'Kuldeep Rana', 'is_active' => true]);
        $sunil = User::factory()->create(['name' => 'Sunil Rana', 'is_active' => true]);
        $kuldeep->assignRole(StaffJobRole::Teacher->value);
        $sunil->assignRole(StaffJobRole::Teacher->value);

        $batch = Batch::query()->create([
            'name' => 'Class 10 - A',
            'section' => 'A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        foreach ([$kuldeep, $sunil] as $teacher) {
            BatchStaffAssignment::query()->create([
                'batch_id' => $batch->id,
                'user_id' => $teacher->id,
                'role' => BatchStaffRole::SubjectTeacher,
                'course_subject_id' => $subject->id,
            ]);
        }

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
                'assigned_by_user_id' => $kuldeep->id,
            ]);
        }

        return [$batch, $subject, $kuldeep, $sunil, $rahul, $anita];
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
}
