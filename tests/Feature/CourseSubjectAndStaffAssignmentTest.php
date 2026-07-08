<?php

namespace Tests\Feature;

use App\Enums\BatchStaffRole;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\DurationType;
use App\Enums\ProgrammeCategory;
use App\Enums\RoleName;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use App\Models\User;
use App\Services\BatchStaffAssignmentService;
use App\Services\CourseSubjectService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CourseSubjectAndStaffAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleName::Staff->value);
    }

    public function test_course_subject_sync_creates_and_updates_subjects(): void
    {
        $course = $this->createCourse();

        app(CourseSubjectService::class)->sync($course, [
            ['name' => 'English', 'code' => 'ENG', 'default_max_marks' => 100],
            ['name' => 'Mathematics', 'code' => 'MAT', 'default_max_marks' => 100],
        ]);

        $this->assertDatabaseCount('course_subjects', 2);

        app(CourseSubjectService::class)->sync($course, [
            ['name' => 'English', 'code' => 'ENG', 'default_max_marks' => 50],
            ['name' => 'Science', 'default_max_marks' => 100],
        ]);

        $this->assertDatabaseCount('course_subjects', 2);
        $this->assertDatabaseHas('course_subjects', [
            'course_id' => $course->id,
            'name' => 'English',
            'default_max_marks' => 50,
        ]);
        $this->assertDatabaseMissing('course_subjects', [
            'course_id' => $course->id,
            'name' => 'Mathematics',
        ]);
    }

    public function test_course_subject_sync_rejects_duplicate_names_in_form(): void
    {
        $course = $this->createCourse();

        $this->expectException(ValidationException::class);

        app(CourseSubjectService::class)->sync($course, [
            ['name' => 'English'],
            ['name' => 'english'],
        ]);
    }

    public function test_batch_staff_assignment_sync_stores_lead_and_subject_teachers(): void
    {
        $course = $this->createCourse();
        $session = AcademicSession::query()->create([
            'name' => '2025–26',
            'code' => '2025-26',
            'starts_on' => '2025-04-01',
            'ends_on' => '2026-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);
        $lead = $this->createStaff('Priya');
        $englishTeacher = $this->createStaff('Amit');
        $mathTeacher = $this->createStaff('Kapil');
        $trainer = $this->createStaff('Trainer');

        app(CourseSubjectService::class)->sync($course, [
            ['name' => 'English'],
            ['name' => 'Mathematics'],
        ]);

        $course->load('subjects');
        $english = $course->subjects->firstWhere('name', 'English');
        $maths = $course->subjects->firstWhere('name', 'Mathematics');

        $batch = Batch::query()->create([
            'name' => 'Class 5-A',
            'section' => 'A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'trainer_user_id' => $trainer->id,
            'status' => BatchStatus::Active,
        ]);

        app(BatchStaffAssignmentService::class)->sync($batch, $lead->id, [
            ['course_subject_id' => $english->id, 'user_id' => $englishTeacher->id],
            ['course_subject_id' => $maths->id, 'user_id' => $mathTeacher->id],
        ]);

        $this->assertDatabaseHas('batch_staff_assignments', [
            'batch_id' => $batch->id,
            'user_id' => $lead->id,
            'role' => BatchStaffRole::LeadTeacher->value,
            'course_subject_id' => null,
        ]);
        $this->assertDatabaseHas('batch_staff_assignments', [
            'batch_id' => $batch->id,
            'user_id' => $englishTeacher->id,
            'role' => BatchStaffRole::SubjectTeacher->value,
            'course_subject_id' => $english->id,
        ]);

        $assignments = app(BatchStaffAssignmentService::class)->assignmentsForUser($englishTeacher);

        $this->assertCount(1, $assignments);
        $this->assertSame(BatchStaffRole::SubjectTeacher, $assignments[0]['role']);
        $this->assertSame('English', $assignments[0]['course_subject']?->name);
    }

    public function test_batch_staff_form_state_lists_all_course_subjects(): void
    {
        $course = $this->createCourse();
        $session = AcademicSession::query()->create([
            'name' => '2025–26',
            'code' => '2025-26',
            'starts_on' => '2025-04-01',
            'ends_on' => '2026-03-31',
            'is_current' => false,
            'is_active' => true,
        ]);
        $trainer = $this->createStaff('Trainer');

        app(CourseSubjectService::class)->sync($course, [
            ['name' => 'English'],
            ['name' => 'Mathematics'],
        ]);

        $batch = Batch::query()->create([
            'name' => 'Class 5-A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'trainer_user_id' => $trainer->id,
            'status' => BatchStatus::Active,
        ]);

        $state = app(BatchStaffAssignmentService::class)->formStateForBatch($batch);

        $this->assertNull($state['lead_teacher_user_id']);
        $this->assertCount(2, $state['subject_teacher_assignments']);
    }

    private function createStaff(string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'is_active' => true,
        ]);
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }

    private function createCourse(): Course
    {
        return Course::query()->create([
            'name' => 'Class 5',
            'code' => 'SCH-05',
            'programme_category' => ProgrammeCategory::School,
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);
    }
}
