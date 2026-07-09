<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Services\EnquiryService;
use App\Services\StudentDataResetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StudentDataResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_students_removes_pipeline_but_keeps_courses_and_staff(): void
    {
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole(RoleName::SuperAdmin->value);

        $course = Course::query()->create([
            'name' => 'Class 12 Science',
            'code' => 'C12-SCI',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 60000,
            'status' => CourseStatus::Active,
        ]);

        $student = Student::query()->create([
            'name' => 'Import Target',
            'father_name' => 'Parent',
            'date_of_birth' => '2008-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876501234',
            'status' => StudentStatus::Enquiry,
            'portal_password' => bcrypt('Student@2026'),
        ]);

        app(EnquiryService::class)->create([
            'name' => $student->name,
            'father_name' => $student->father_name,
            'date_of_birth' => $student->date_of_birth->toDateString(),
            'gender' => $student->gender->value,
            'mobile' => $student->mobile,
            'course_id' => $course->id,
        ], $staff, LeadSource::WalkIn);

        $this->assertSame(1, Student::query()->count());
        $this->assertSame(1, Enquiry::query()->count());

        app(StudentDataResetService::class)->reset();

        $this->assertSame(0, Student::query()->count());
        $this->assertSame(0, Enquiry::query()->count());
        $this->assertSame(1, Course::query()->count());
        $this->assertSame(1, User::query()->count());
    }

    public function test_reset_students_command_requires_confirmation(): void
    {
        Student::query()->create([
            'name' => 'To Delete',
            'father_name' => 'Parent',
            'date_of_birth' => '2008-01-01',
            'gender' => Gender::Female,
            'mobile' => '9876509999',
            'status' => StudentStatus::Enquiry,
            'portal_password' => bcrypt('Student@2026'),
        ]);

        $this->artisan('crm:reset-students')
            ->expectsConfirmation('Delete all student data now?', 'no')
            ->assertSuccessful();

        $this->assertSame(1, Student::query()->count());
    }

    public function test_reset_students_command_force_option_clears_data(): void
    {
        Student::query()->create([
            'name' => 'To Delete',
            'father_name' => 'Parent',
            'date_of_birth' => '2008-01-01',
            'gender' => Gender::Female,
            'mobile' => '9876508888',
            'status' => StudentStatus::Enquiry,
            'portal_password' => bcrypt('Student@2026'),
        ]);

        $this->artisan('crm:reset-students --force')
            ->assertSuccessful();

        $this->assertSame(0, Student::query()->count());
    }

    public function test_reset_students_clears_exam_and_result_tables_when_present(): void
    {
        if (! Schema::hasTable('exam_windows') || ! Schema::hasTable('result_declarations')) {
            $this->markTestSkipped('Exam/result tables not migrated.');
        }

        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole(RoleName::SuperAdmin->value);

        $course = Course::query()->create([
            'name' => 'Reset Exam Programme',
            'code' => 'RESET-EX',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $batch = \App\Models\Batch::query()->create([
            'name' => 'Section A',
            'section' => 'A',
            'course_id' => $course->id,
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => \App\Enums\BatchStatus::Active,
        ]);

        $this->seed(\Database\Seeders\ActivityTypeSeeder::class);
        $activityTypeId = \App\Models\ActivityType::query()->firstOrFail()->id;

        \App\Models\ExamWindow::query()->create([
            'batch_id' => $batch->id,
            'activity_type_id' => $activityTypeId,
            'test_name' => 'Unit Test 1',
            'session_date' => '2026-07-09',
            'test_key' => 'unit-test-1',
            'status' => 'draft',
        ]);

        \App\Models\ResultDeclaration::query()->create([
            'group_key' => 'unit-test-1',
            'test_name' => 'Unit Test 1',
            'session_date' => '2026-07-09',
            'batch_id' => $batch->id,
            'activity_type_id' => $activityTypeId,
            'status' => \App\Enums\ResultDeclarationStatus::Draft,
        ]);

        app(StudentDataResetService::class)->reset();

        $this->assertSame(0, \App\Models\ExamWindow::query()->count());
        $this->assertSame(0, \App\Models\ResultDeclaration::query()->count());
        $this->assertSame(1, \App\Models\Batch::query()->count());
    }
}
