<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\DurationType;
use App\Enums\ProgrammeCategory;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use App\Services\BatchService;
use App\Services\CourseLifecycleService;
use App\Support\InstituteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_site_hides_programmes_without_sections(): void
    {
        $withSection = Course::query()->create([
            'name' => 'With Section',
            'code' => 'SEC-001',
            'programme_category' => ProgrammeCategory::School,
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'fee' => 1000,
            'status' => CourseStatus::Active,
            'show_on_website' => true,
        ]);

        $orphan = Course::query()->create([
            'name' => 'Orphan Programme',
            'code' => 'ORP-001',
            'programme_category' => ProgrammeCategory::School,
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'fee' => 1000,
            'status' => CourseStatus::Active,
            'show_on_website' => true,
        ]);

        $session = AcademicSession::query()->create([
            'name' => '2026-27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        Batch::query()->create([
            'course_id' => $withSection->id,
            'academic_session_id' => $session->id,
            'name' => 'With Section A',
            'section' => 'A',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        $publicIds = InstituteProfile::publicCoursesQuery(Course::query())->pluck('id')->all();

        $this->assertSame([$withSection->id], $publicIds);
        $this->assertNotContains($orphan->id, $publicIds);
    }

    public function test_deleting_last_section_removes_programme_from_public_site(): void
    {
        $course = Course::query()->create([
            'name' => 'Delete Me',
            'code' => 'DEL-001',
            'programme_category' => ProgrammeCategory::School,
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'fee' => 1000,
            'status' => CourseStatus::Active,
            'show_on_website' => true,
        ]);

        $session = AcademicSession::query()->create([
            'name' => '2026-27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $batch = Batch::query()->create([
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'name' => 'Delete Me A',
            'section' => 'A',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        app(BatchService::class)->deleteSection($batch);

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
        $this->assertSame([], InstituteProfile::publicCoursesQuery(Course::query())->pluck('id')->all());
    }

    public function test_delete_programme_with_all_sections_removes_class(): void
    {
        $course = Course::query()->create([
            'name' => 'Full Delete',
            'code' => 'FULL-001',
            'programme_category' => ProgrammeCategory::School,
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'fee' => 1000,
            'status' => CourseStatus::Active,
            'show_on_website' => true,
        ]);

        $session = AcademicSession::query()->create([
            'name' => '2026-27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        foreach (['A', 'B'] as $section) {
            Batch::query()->create([
                'course_id' => $course->id,
                'academic_session_id' => $session->id,
                'name' => "Full Delete {$section}",
                'section' => $section,
                'start_date' => '2026-04-01',
                'end_date' => '2027-03-31',
                'status' => BatchStatus::Active,
            ]);
        }

        app(CourseLifecycleService::class)->deleteProgrammeWithAllSections($course);

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
        $this->assertDatabaseCount('batches', 0);
    }
}
