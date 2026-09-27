<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\DurationType;
use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Enums\StandardCoursePlanStatus;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseSubject;
use App\Models\Setting;
use App\Models\StandardCourseChapter;
use App\Models\StandardCoursePlan;
use App\Models\StandardCourseTopic;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\StandardCoursePlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class StandardCoursePlanServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_plan_cannot_be_marked_ready(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan();

        $this->expectException(ValidationException::class);

        app(StandardCoursePlanService::class)->markReady($plan, $user);
    }

    public function test_plan_with_a_topic_can_be_marked_ready(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan();
        $chapter = StandardCourseChapter::query()->create([
            'standard_course_plan_id' => $plan->id,
            'name' => 'Electrostatics',
            'sort_order' => 1,
        ]);
        StandardCourseTopic::query()->create([
            'standard_course_chapter_id' => $chapter->id,
            'name' => "Coulomb's Law",
            'planned_minutes' => 120,
            'reference_book' => 'HC Verma',
            'dpp_count' => 2,
            'quiz_count' => 1,
            'test_count' => 0,
            'sort_order' => 1,
        ]);

        app(StandardCoursePlanService::class)->markReady($plan, $user);

        $plan->refresh();

        $this->assertSame(StandardCoursePlanStatus::Ready, $plan->status);
        $this->assertSame(120, $plan->totalPlannedMinutes());
        $this->assertSame($user->id, $plan->ready_by_user_id);
    }

    public function test_subject_from_another_programme_is_rejected(): void
    {
        $plan = $this->makePlan();
        $otherCourse = Course::query()->create([
            'name' => '11th Board',
            'code' => '11-BOARD',
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'status' => CourseStatus::Active,
        ]);
        $otherSubject = CourseSubject::query()->create([
            'course_id' => $otherCourse->id,
            'name' => 'Physics',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(StandardCoursePlanService::class)->assertCanSave([
            'academic_session_id' => $plan->academic_session_id,
            'course_id' => $plan->course_id,
            'course_subject_id' => $otherSubject->id,
        ], $plan->id);
    }

    public function test_same_year_programme_and_subject_cannot_have_two_plans(): void
    {
        $plan = $this->makePlan();

        $this->expectException(ValidationException::class);

        app(StandardCoursePlanService::class)->assertCanSave([
            'academic_session_id' => $plan->academic_session_id,
            'course_id' => $plan->course_id,
            'course_subject_id' => $plan->course_subject_id,
        ]);
    }

    public function test_ready_plan_returns_to_draft_when_topics_are_removed(): void
    {
        $user = User::factory()->create();
        $plan = $this->makePlan();
        $chapter = StandardCourseChapter::query()->create([
            'standard_course_plan_id' => $plan->id,
            'name' => 'Electrostatics',
            'sort_order' => 1,
        ]);
        $topic = StandardCourseTopic::query()->create([
            'standard_course_chapter_id' => $chapter->id,
            'name' => 'Electric Field',
            'planned_minutes' => 240,
            'dpp_count' => 3,
            'quiz_count' => 1,
            'test_count' => 1,
            'sort_order' => 1,
        ]);

        $service = app(StandardCoursePlanService::class);
        $service->markReady($plan, $user);
        $topic->delete();
        $service->keepReadyOnlyWhenContentAllows($plan->refresh());

        $this->assertSame(StandardCoursePlanStatus::Draft, $plan->refresh()->status);
    }

    public function test_full_results_license_includes_teacher_tracking_even_if_the_saved_list_is_old(): void
    {
        $license = app(LicenseService::class);
        $license->save([
            'plan' => LicensePlan::FullResults->value,
            'expires_at' => now()->addYear()->toDateString(),
        ]);

        $payload = Setting::getValue(LicenseService::PAYLOAD_KEY);
        $payload['features'] = [LicenseFeature::Attendance->value];
        Setting::setValue(LicenseService::PAYLOAD_KEY, $payload, 'license');

        $sign = new \ReflectionMethod(LicenseService::class, 'sign');
        $sign->setAccessible(true);
        Setting::setValue(
            LicenseService::SIGNATURE_KEY,
            $sign->invoke($license, $payload),
            'license',
        );
        Setting::flushValueCache();

        $this->assertTrue($license->hasFeature(LicenseFeature::TeacherTracking));
    }

    private function makePlan(): StandardCoursePlan
    {
        $session = AcademicSession::query()->create([
            'name' => '2026-27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);
        $course = Course::query()->create([
            'name' => '11th JEE',
            'code' => '11-JEE',
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'status' => CourseStatus::Active,
        ]);
        $subject = CourseSubject::query()->create([
            'course_id' => $course->id,
            'name' => 'Physics',
            'code' => 'PHY',
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return StandardCoursePlan::query()->create([
            'academic_session_id' => $session->id,
            'course_id' => $course->id,
            'course_subject_id' => $subject->id,
            'status' => StandardCoursePlanStatus::Draft,
        ]);
    }
}
