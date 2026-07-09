<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\DurationType;
use App\Enums\ProgrammeCategory;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use App\Support\InstituteProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicCourseVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_site_only_shows_active_courses_marked_for_website(): void
    {
        $visible = Course::query()->create([
            'name' => 'Visible Programme',
            'code' => 'VIS-001',
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
            'course_id' => $visible->id,
            'academic_session_id' => $session->id,
            'name' => 'Visible A',
            'section' => 'A',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        Course::query()->create([
            'name' => 'Hidden From Website',
            'code' => 'HID-001',
            'programme_category' => ProgrammeCategory::School,
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'fee' => 1000,
            'status' => CourseStatus::Active,
            'show_on_website' => false,
        ]);

        Course::query()->create([
            'name' => 'Inactive Programme',
            'code' => 'OFF-001',
            'programme_category' => ProgrammeCategory::School,
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'fee' => 1000,
            'status' => CourseStatus::Inactive,
            'show_on_website' => true,
        ]);

        $publicIds = InstituteProfile::publicCoursesQuery(Course::query())->pluck('id')->all();

        $this->assertSame([$visible->id], $publicIds);
    }

    public function test_admin_course_options_exclude_system_undecided_course(): void
    {
        Course::query()->create([
            'name' => 'Course Not Decided',
            'code' => 'GEN-UNDECIDED',
            'programme_category' => ProgrammeCategory::Custom,
            'duration' => 1,
            'duration_type' => DurationType::Months,
            'fee' => 0,
            'status' => CourseStatus::Active,
            'show_on_website' => false,
        ]);

        $myProgramme = Course::query()->create([
            'name' => 'My Programme',
            'code' => 'MY-001',
            'programme_category' => ProgrammeCategory::Custom,
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'fee' => 5000,
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
            'course_id' => $myProgramme->id,
            'academic_session_id' => $session->id,
            'name' => 'My Programme A',
            'section' => 'A',
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        $options = InstituteProfile::activeCourseOptions();

        $this->assertArrayHasKey($myProgramme->id, $options);
        $this->assertArrayNotHasKey(Course::query()->where('code', 'GEN-UNDECIDED')->value('id'), $options);
    }
}
