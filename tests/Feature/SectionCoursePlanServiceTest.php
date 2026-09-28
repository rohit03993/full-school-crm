<?php

namespace Tests\Feature;

use App\Enums\BatchStaffRole;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\DurationType;
use App\Enums\Gender;
use App\Enums\RoleName;
use App\Enums\SectionCoursePlanStatus;
use App\Enums\StaffJobRole;
use App\Enums\StandardCoursePlanStatus;
use App\Enums\StandardCoursePracticalKind;
use App\Enums\StudentStatus;
use App\Filament\Pages\StudentProfilePage;
use App\Filament\Resources\SectionCoursePlans\Pages\EditSectionCoursePlan;
use App\Filament\Resources\SectionCoursePlans\Pages\ListSectionCoursePlans;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\CourseSubject;
use App\Models\StandardCourseChapter;
use App\Models\StandardCoursePlan;
use App\Models\StandardCoursePractical;
use App\Models\StandardCourseTopic;
use App\Models\Student;
use App\Models\User;
use App\Services\SectionCoursePlanService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SectionCoursePlanServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_ready_plan_can_be_copied_onto_one_section_and_finalized(): void
    {
        [$plan, $batch, $teacher, $head] = $this->readyPlanOnSection();
        $service = app(SectionCoursePlanService::class);

        $sectionPlan = $service->copyFromStandard($plan, $batch, $head);

        $this->assertSame(SectionCoursePlanStatus::Draft, $sectionPlan->status);
        $this->assertSame(0, $sectionPlan->version);
        $this->assertSame(60, $sectionPlan->lecture_minutes);
        $this->assertSame(8641, $sectionPlan->topics()->first()->planned_minutes);
        $this->assertSame(1, $sectionPlan->practicals()->count());

        $service->submit($sectionPlan->refresh(), $teacher);

        $this->expectException(ValidationException::class);
        $service->finalize($sectionPlan->refresh(), $teacher);
    }

    public function test_the_teacher_who_submitted_cannot_finalize_and_another_head_can(): void
    {
        [$plan, $batch, $teacher, $head] = $this->readyPlanOnSection();
        $service = app(SectionCoursePlanService::class);
        $sectionPlan = $service->copyFromStandard($plan, $batch, $head);

        $teacherHead = User::factory()->create(['is_active' => true, 'name' => 'Teacher Head']);
        $teacherHead->assignRole(StaffJobRole::AcademicCoordinator->value);
        BatchStaffAssignment::query()->where('user_id', $teacher->id)->update(['user_id' => $teacherHead->id]);

        $service->submit($sectionPlan->refresh(), $teacherHead);

        try {
            $service->finalize($sectionPlan->refresh(), $teacherHead);
            $this->fail('The person who submitted must not finalize.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('cannot decide', $exception->getMessage());
        }

        $service->finalize($sectionPlan->refresh(), $head);

        $this->assertSame(SectionCoursePlanStatus::Final, $sectionPlan->refresh()->status);
        $this->assertSame(1, $sectionPlan->version);
        $this->assertSame(1, $sectionPlan->versions()->count());
    }

    public function test_send_back_needs_a_comment_and_a_later_change_keeps_the_old_student_topics(): void
    {
        [$plan, $batch, $teacher, $head, $student] = $this->readyPlanOnSection(withStudent: true);
        $service = app(SectionCoursePlanService::class);
        $sectionPlan = $service->copyFromStandard($plan, $batch, $head);
        $service->submit($sectionPlan->refresh(), $teacher);

        try {
            $service->sendBack($sectionPlan->refresh(), $head, '   ');
            $this->fail('A blank comment must be refused.');
        } catch (ValidationException) {
            $this->assertSame(SectionCoursePlanStatus::Submitted, $sectionPlan->refresh()->status);
        }

        $service->sendBack($sectionPlan->refresh(), $head, 'Add one more topic.');
        $this->assertSame(SectionCoursePlanStatus::SentBack, $sectionPlan->refresh()->status);

        $service->submit($sectionPlan->refresh(), $teacher);
        $service->finalize($sectionPlan->refresh(), $head);

        $topics = $service->finalTopicsForStudent($student->fresh('activeBatchStudent'));
        $this->assertSame('Coulomb Law', $topics[0]['chapters'][0]['topics'][0]['name']);
        $this->assertSame(2, $topics[0]['chapters'][0]['topics'][0]['dpp_count']);
        $this->assertSame('Anita Physics', $topics[0]['faculty']);
        $this->assertArrayNotHasKey('planned_minutes', $topics[0]['chapters'][0]['topics'][0]);

        $service->openChange($sectionPlan->refresh(), $head, 'Split the topic.');
        $sectionPlan->topics()->first()->update(['name' => 'Changed name', 'planned_minutes' => 90]);

        $stillFinal = $service->finalTopicsForStudent($student->fresh('activeBatchStudent'));
        $this->assertSame('Coulomb Law', $stillFinal[0]['chapters'][0]['topics'][0]['name']);
        $this->assertSame(SectionCoursePlanStatus::Draft, $sectionPlan->refresh()->status);
        $this->assertSame(1, $sectionPlan->version);
    }

    public function test_a_draft_course_plan_and_a_second_copy_are_refused(): void
    {
        [$plan, $batch, $teacher, $head] = $this->readyPlanOnSection();
        $service = app(SectionCoursePlanService::class);
        $plan->update(['status' => StandardCoursePlanStatus::Draft]);

        try {
            $service->copyFromStandard($plan->refresh(), $batch, $head);
            $this->fail('A draft course plan must not be copied.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('ready', $exception->getMessage());
        }

        $plan->update(['status' => StandardCoursePlanStatus::Ready]);
        $service->copyFromStandard($plan->refresh(), $batch, $head);

        $this->expectException(ValidationException::class);
        $service->copyFromStandard($plan->refresh(), $batch, $head);
    }

    public function test_section_plan_screen_lets_the_teacher_submit_and_hides_finalize(): void
    {
        [$plan, $batch, $teacher, $head] = $this->readyPlanOnSection();
        $sectionPlan = app(SectionCoursePlanService::class)->copyFromStandard($plan, $batch, $head);

        $this->actingAs($teacher);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(EditSectionCoursePlan::class, ['record' => $sectionPlan->getRouteKey()])
            ->assertSuccessful()
            ->assertSee('Submit')
            ->assertSee('Electrostatics')
            ->assertSee('Lecture duration')
            ->assertDontSee('Finalize');
    }

    public function test_student_profile_and_portal_hide_minutes(): void
    {
        [$plan, $batch, $teacher, $head, $student] = $this->readyPlanOnSection(withStudent: true);
        $service = app(SectionCoursePlanService::class);
        $sectionPlan = $service->copyFromStandard($plan, $batch, $head);
        $service->submit($sectionPlan->refresh(), $teacher);
        $service->finalize($sectionPlan->refresh(), $head);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->set('profileTab', 'topics')
            ->assertSee('Coulomb Law')
            ->assertSee('DPP 2')
            ->assertSee('Faculty: Anita Physics')
            ->assertDontSee('8641');

        $this->withSession(['student_portal_id' => $student->id])
            ->get(route('portal.topics.index'))
            ->assertOk()
            ->assertSee('Coulomb Law')
            ->assertSee('DPP 2')
            ->assertDontSee('8641');
    }

    public function test_academic_head_can_copy_from_the_section_plan_list(): void
    {
        [$plan, $batch, $teacher, $head] = $this->readyPlanOnSection();

        $this->actingAs($head);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListSectionCoursePlans::class)
            ->callAction('copyOntoSection', data: [
                'standard_course_plan_id' => $plan->id,
                'batch_id' => $batch->id,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('section_course_plans', [
            'batch_id' => $batch->id,
            'course_subject_id' => $plan->course_subject_id,
            'status' => SectionCoursePlanStatus::Draft->value,
        ]);
    }

    /**
     * @return array{0: StandardCoursePlan, 1: Batch, 2: User, 3: User, 4?: Student}
     */
    private function readyPlanOnSection(bool $withStudent = false): array
    {
        Role::findOrCreate(RoleName::SuperAdmin->value, 'web');

        $session = AcademicSession::query()->create([
            'name' => '2026-27',
            'code' => 'SEC-2026',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);
        $course = Course::query()->create([
            'name' => 'Class 11',
            'code' => 'SEC-11',
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'status' => CourseStatus::Active,
        ]);
        $subject = CourseSubject::query()->create([
            'course_id' => $course->id,
            'name' => 'Physics',
            'code' => 'SEC-PHY',
            'sort_order' => 1,
            'is_active' => true,
        ]);
        $plan = StandardCoursePlan::query()->create([
            'academic_session_id' => $session->id,
            'course_id' => $course->id,
            'course_subject_id' => $subject->id,
            'lecture_minutes' => 60,
            'status' => StandardCoursePlanStatus::Ready,
        ]);
        $chapter = StandardCourseChapter::query()->create([
            'standard_course_plan_id' => $plan->id,
            'name' => 'Electrostatics',
            'estimated_marks' => 6,
            'sort_order' => 1,
        ]);
        StandardCourseTopic::query()->create([
            'standard_course_chapter_id' => $chapter->id,
            'name' => 'Coulomb Law',
            'planned_minutes' => 8641,
            'reference_book' => 'NCERT Class 11 Physics',
            'dpp_count' => 2,
            'quiz_count' => 1,
            'test_count' => 0,
            'sort_order' => 1,
        ]);
        StandardCoursePractical::query()->create([
            'standard_course_plan_id' => $plan->id,
            'name' => 'Vernier callipers',
            'kind' => StandardCoursePracticalKind::Experiment,
            'sort_order' => 1,
        ]);

        $teacher = User::factory()->create(['is_active' => true, 'name' => 'Anita Physics']);
        $teacher->assignRole(StaffJobRole::Teacher->value);
        $head = User::factory()->create(['is_active' => true, 'name' => 'Academic Head']);
        $head->assignRole(StaffJobRole::AcademicCoordinator->value);

        $batch = Batch::query()->create([
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'name' => 'Class 11-A',
            'section' => 'A',
            'trainer_user_id' => $head->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);
        $batch->subjects()->attach($subject->id, ['sort_order' => 1]);
        BatchStaffAssignment::query()->create([
            'batch_id' => $batch->id,
            'user_id' => $teacher->id,
            'role' => BatchStaffRole::SubjectTeacher,
            'course_subject_id' => $subject->id,
        ]);

        $result = [$plan, $batch, $teacher, $head];

        if ($withStudent) {
            $student = Student::query()->create([
                'name' => 'Riya Student',
                'father_name' => 'Parent',
                'date_of_birth' => '2010-01-01',
                'gender' => Gender::Female,
                'mobile' => '9876501122',
                'status' => StudentStatus::Enrolled,
            ]);
            BatchStudent::query()->create([
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'is_active' => true,
                'assigned_at' => now(),
                'assigned_by_user_id' => $head->id,
            ]);
            $result[] = $student;
        }

        return $result;
    }
}
