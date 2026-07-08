<?php

namespace Tests\Unit;

use App\Enums\BatchShift;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\DurationType;
use App\Enums\ProgrammeCategory;
use App\Enums\RoleName;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use App\Models\User;
use App\Services\ClassSectionService;
use App\Support\ClassSectionLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClassSectionLabelTest extends TestCase
{
    use RefreshDatabase;

    public function test_for_batch_uses_programme_and_section(): void
    {
        $course = Course::query()->create([
            'name' => 'Class 12 Science',
            'code' => 'SCH-12-SCI',
            'programme_category' => ProgrammeCategory::School,
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'fee' => 100000,
            'status' => CourseStatus::Active,
        ]);

        $session = AcademicSession::query()->create([
            'name' => '2025–26',
            'code' => '2025-26',
            'starts_on' => '2025-04-01',
            'ends_on' => '2026-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $trainer = User::factory()->create(['is_active' => true]);

        $batch = Batch::query()->create([
            'name' => 'Class 12-A',
            'section' => 'A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'trainer_user_id' => $trainer->id,
            'shift' => BatchShift::Morning,
            'status' => BatchStatus::Active,
        ]);

        $this->assertSame(
            'Class 12 Science · Section A · 2025–26 · Morning',
            ClassSectionLabel::forBatch($batch),
        );
    }

    public function test_suggest_batch_name_avoids_duplication(): void
    {
        $this->assertSame('Class 12-A', ClassSectionLabel::suggestBatchName('Class 12', 'A'));
        $this->assertSame('IIT JEE Class 12-A', ClassSectionLabel::suggestBatchName('IIT JEE Class 12', 'A'));
    }

    public function test_service_create_with_existing_course_and_new_section(): void
    {
        Role::findOrCreate(RoleName::Staff->value);

        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'SCH-10',
            'programme_category' => ProgrammeCategory::School,
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'fee' => 80000,
            'status' => CourseStatus::Active,
        ]);

        $session = AcademicSession::query()->create([
            'name' => '2025–26',
            'code' => '2025-26',
            'starts_on' => '2025-04-01',
            'ends_on' => '2026-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $trainer = User::factory()->create(['is_active' => true]);
        $trainer->assignRole(RoleName::Staff->value);

        $result = app(ClassSectionService::class)->create([
            'programme_mode' => 'existing',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'section' => 'A',
            'trainer_user_id' => $trainer->id,
        ]);

        $this->assertSame($course->id, $result['batch']->course_id);
        $this->assertSame('A', $result['batch']->section);
        $this->assertSame('Class 10-A', $result['batch']->name);
    }

    public function test_service_create_new_programme_and_section(): void
    {
        Role::findOrCreate(RoleName::Staff->value);

        $session = AcademicSession::query()->create([
            'name' => '2025–26',
            'code' => '2025-26',
            'starts_on' => '2025-04-01',
            'ends_on' => '2026-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $trainer = User::factory()->create(['is_active' => true]);
        $trainer->assignRole(RoleName::Staff->value);

        $result = app(ClassSectionService::class)->create([
            'programme_mode' => 'new',
            'programme_name' => 'IIT JEE Class 12',
            'programme_code' => 'JEE-12',
            'duration' => 1,
            'duration_type' => DurationType::Years->value,
            'fee' => 120000,
            'course_subjects' => [
                ['name' => 'Physics'],
                ['name' => 'Chemistry'],
            ],
            'academic_session_id' => $session->id,
            'section' => 'A',
            'trainer_user_id' => $trainer->id,
        ]);

        $this->assertDatabaseHas('courses', ['name' => 'IIT JEE Class 12', 'code' => 'JEE-12']);
        $this->assertDatabaseCount('course_subjects', 2);
        $this->assertSame('IIT JEE Class 12', $result['batch']->course->name);
        $this->assertSame('A', $result['batch']->section);
    }
}
