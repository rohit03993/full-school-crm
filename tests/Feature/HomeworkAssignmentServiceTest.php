<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
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
            'status' => BatchStatus::Active,
        ]);

        $student = Student::factory()->create([
            'name' => 'Rohit',
            'mobile' => '8320936486',
            'status' => StudentStatus::Enrolled,
        ]);

        $enquiry = Enquiry::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
        ]);

        $admission = Admission::factory()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
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
            'is_active' => true,
        ]);

        return [$student->fresh(), $batch, $staff];
    }
}
