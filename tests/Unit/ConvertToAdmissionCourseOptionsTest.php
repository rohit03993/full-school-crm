<?php

namespace Tests\Unit;

use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\DurationType;
use App\Enums\ProgrammeCategory;
use App\Filament\Forms\ConvertToAdmissionFormSchema;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use App\Models\User;
use App\Support\InstituteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvertToAdmissionCourseOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_course_options_only_include_programmes_with_active_sections_in_current_year(): void
    {
        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $trainer = User::factory()->create(['is_active' => true]);

        $enrollable = Course::query()->create([
            'name' => '12th JEE',
            'code' => 'JEE-12',
            'programme_category' => ProgrammeCategory::Coaching,
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'fee' => 185000,
            'status' => CourseStatus::Active,
        ]);

        $orphan = Course::query()->create([
            'name' => 'Class 12 Commerce',
            'code' => 'COM-12',
            'programme_category' => ProgrammeCategory::School,
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'fee' => 120000,
            'status' => CourseStatus::Active,
        ]);

        Batch::query()->create([
            'name' => '12th JEE-A',
            'section' => 'A',
            'course_id' => $enrollable->id,
            'academic_session_id' => $session->id,
            'trainer_user_id' => $trainer->id,
            'status' => BatchStatus::Active,
        ]);

        $options = ConvertToAdmissionFormSchema::courseOptions();

        $this->assertCount(1, $options);
        $this->assertArrayHasKey($enrollable->id, $options);
        $this->assertArrayNotHasKey($orphan->id, $options);
        $this->assertTrue(InstituteProfile::isEnrollableCourseId($enrollable->id));
        $this->assertFalse(InstituteProfile::isEnrollableCourseId($orphan->id));
    }
}
