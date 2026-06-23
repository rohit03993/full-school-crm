<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\DurationType;
use App\Enums\ProgrammeCategory;
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

        Course::query()->create([
            'name' => 'My Programme',
            'code' => 'MY-001',
            'programme_category' => ProgrammeCategory::Custom,
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'fee' => 5000,
            'status' => CourseStatus::Active,
            'show_on_website' => true,
        ]);

        $options = InstituteProfile::activeCourseOptions();

        $this->assertArrayHasKey(Course::query()->where('code', 'MY-001')->value('id'), $options);
        $this->assertArrayNotHasKey(Course::query()->where('code', 'GEN-UNDECIDED')->value('id'), $options);
    }
}
