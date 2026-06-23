<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\ActivityType;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\ActivityAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SessionAttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_find_or_create_session_for_workshop_type(): void
    {
        $staff = $this->createStaffUser();
        $batch = $this->createBatch($staff);
        $student = $this->createStudentInBatch($batch, $staff);

        $workshop = ActivityType::query()->create([
            'name' => 'Workshop',
            'slug' => 'workshop',
            'field_schema' => [
                ['key' => 'topic', 'label' => 'Topic', 'type' => 'text'],
            ],
            'is_enabled' => true,
        ]);

        $service = app(ActivityAttendanceService::class);

        $session = $service->findOrCreateSession(
            $workshop->id,
            $batch->id,
            '2026-06-20',
            'Robotics demo',
            $staff,
        );

        $this->assertSame('Robotics demo', $session->title);

        $saved = $service->saveMarks($session, [$student->id => true], $staff);

        $this->assertSame(1, $saved);

        $again = $service->findOrCreateSession(
            $workshop->id,
            $batch->id,
            '2026-06-20',
            'Robotics demo',
            $staff,
        );

        $this->assertTrue($again->is($session));
        $this->assertTrue($service->marksFor($again)[$student->id]);
    }

    public function test_session_attendance_appears_in_student_profile_query(): void
    {
        $staff = $this->createStaffUser();
        $batch = $this->createBatch($staff);
        $student = $this->createStudentInBatch($batch, $staff);

        $workshop = ActivityType::query()->create([
            'name' => 'Event',
            'slug' => 'event-test',
            'field_schema' => [['key' => 'venue', 'label' => 'Venue', 'type' => 'text']],
            'is_enabled' => true,
        ]);

        $service = app(ActivityAttendanceService::class);
        $session = $service->findOrCreateSession(
            $workshop->id,
            $batch->id,
            '2026-06-21',
            'Orientation day',
            $staff,
        );

        $service->saveMarks($session, [$student->id => false], $staff);

        $records = $service->sessionAttendanceRecordsForStudent($student->fresh());
        $summary = $service->attendanceSummaryForStudent($student->fresh(), $workshop);

        $this->assertCount(1, $records);
        $this->assertFalse($records->first()->is_present);
        $this->assertSame(0, $summary['present']);
        $this->assertSame(1, $summary['total']);
    }

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createBatch(User $staff): Batch
    {
        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'SES-ATT',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        return Batch::query()->create([
            'course_id' => $course->id,
            'name' => 'Batch A',
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);
    }

    protected function createStudentInBatch(Batch $batch, User $staff): Student
    {
        $student = Student::query()->create([
            'name' => 'Workshop Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876500001',
            'status' => StudentStatus::Enquiry,
        ]);

        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $staff->id,
        ]);

        return $student;
    }
}
