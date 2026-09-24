<?php

namespace Tests\Feature;

use App\Enums\BatchStaffRole;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\HomeworkAssignmentStatus;
use App\Enums\HomeworkCheckStatus;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\StudentStatus;
use App\Enums\WhatsAppMessageSource;
use App\Enums\WhatsAppSendActor;
use App\Filament\Pages\HomeworkCheckPage;
use App\Filament\Pages\HomeworkPage;
use App\Filament\Pages\HomeworkReviewPage;
use App\Filament\Pages\SubmitHomeworkPage;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\CourseSubject;
use App\Models\HomeworkAssignment;
use App\Models\MetaWhatsAppMessage;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\CrmPermissionSyncService;
use App\Services\HomeworkCheckService;
use App\Services\HomeworkSubmissionService;
use App\Services\MetaWhatsAppCostEstimator;
use App\Support\CombinedHomeworkWhatsAppTemplate;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HomeworkSubmissionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleName::SuperAdmin->value);
        Role::findOrCreate(RoleName::Staff->value);

        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-test-token'), 'meta_whatsapp');
        Setting::flushValueCache();
    }

    public function test_sidebar_registers_only_one_homework_entry(): void
    {
        $this->assertTrue(HomeworkPage::shouldRegisterNavigation());
        $this->assertFalse(SubmitHomeworkPage::shouldRegisterNavigation());
        $this->assertFalse(HomeworkReviewPage::shouldRegisterNavigation());
        $this->assertFalse(HomeworkCheckPage::shouldRegisterNavigation());
        $this->assertFalse(HomeworkAssignmentResource::shouldRegisterNavigation());
        $this->assertFalse(HomeworkAssignmentResource::canCreate());
    }

    public function test_review_page_renders_with_and_without_send_summary(): void
    {
        $data = $this->seedClass();

        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->assertSuccessful()
            ->set('data.batch_id', $data['batch']->id)
            ->assertSuccessful()
            ->set('lastCombinedSendResult', [
                'sent' => 1,
                'failed' => 0,
                'skipped' => 0,
                'currency' => 'INR',
                'unit_cost' => 0.115,
                'estimated_total_cost' => 0.115,
                'recipients' => [
                    ['name' => 'Riya Sharma', 'phone' => '9876500001', 'status' => 'sent', 'error' => null, 'estimated_cost' => 0.115],
                ],
            ])
            ->assertSuccessful()
            ->assertSee('9876500001');
    }

    public function test_combined_send_uses_current_india_utility_rate(): void
    {
        MetaWhatsAppTemplate::query()->create([
            'name' => CombinedHomeworkWhatsAppTemplate::NAME,
            'language' => 'en',
            'status' => 'APPROVED',
            'param_count' => 4,
            'body' => CombinedHomeworkWhatsAppTemplate::BODY,
            'is_active' => true,
            'provider_meta' => ['category' => 'UTILITY'],
            'synced_at' => now(),
        ]);

        $estimate = app(MetaWhatsAppCostEstimator::class)
            ->estimateForTemplate(CombinedHomeworkWhatsAppTemplate::NAME, 'en');

        $this->assertSame('UTILITY', $estimate['category']);
        $this->assertSame(0.115, $estimate['cost_inr']);
    }

    public function test_teacher_submit_creates_submitted_assignment(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $assignment = $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra practice',
            'description' => 'Complete exercise 5.2',
        ]);

        $this->assertSame(HomeworkAssignmentStatus::Submitted, $assignment->status);
        $this->assertNull($assignment->published_at);
        $this->assertSame($data['mathTeacher']->id, $assignment->submitted_by_user_id);
        $this->assertNull($assignment->approved_by_user_id);
    }

    public function test_teacher_cannot_submit_subject_they_do_not_teach(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $this->expectException(ValidationException::class);

        $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['physics']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Physics',
            'description' => 'Optics',
        ]);
    }

    public function test_admin_save_is_approved_immediately(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $assignment = $service->submit($data['admin'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['physics']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Physics',
            'description' => 'Chapter 3 numericals',
        ], asAdmin: true);

        $this->assertSame(HomeworkAssignmentStatus::Approved, $assignment->status);
        $this->assertNotNull($assignment->published_at);
        $this->assertNotNull($assignment->approved_at);
        $this->assertSame($data['admin']->id, $assignment->created_by_user_id);
        $this->assertSame($data['admin']->id, $assignment->approved_by_user_id);
    }

    public function test_academic_coordinator_uses_the_same_review_desk_without_class_assignment(): void
    {
        $data = $this->seedClass();
        app(CrmPermissionSyncService::class)->sync();

        $coordinator = User::factory()->create(['is_active' => true, 'name' => 'Khushi Coordinator']);
        $coordinator->syncRoles([RoleName::Staff->value, StaffJobRole::AcademicCoordinator->value]);

        $this->actingAs($coordinator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertTrue(HomeworkReviewPage::canAccess());
        $this->assertTrue(HomeworkPage::canAccess());

        $service = app(HomeworkSubmissionService::class);
        $assignment = $service->submit($coordinator, [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['physics']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Physics',
            'description' => 'Chapter 3 numericals',
        ], asAdmin: true);

        $this->assertSame(HomeworkAssignmentStatus::Approved, $assignment->status);
        $this->assertSame($coordinator->id, $assignment->created_by_user_id);
        $this->assertSame($coordinator->id, $assignment->submitted_by_user_id);
        $this->assertSame($coordinator->id, $assignment->approved_by_user_id);
    }

    public function test_board_lists_every_subject_with_status(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra',
            'description' => 'Ex 5.2',
        ]);

        $board = $service->boardForClassDate($data['admin'], $data['batch']->id, now()->toDateString());

        $this->assertSame(2, $board['summary']['total']);
        $this->assertSame(1, $board['summary']['submitted']);
        $this->assertSame(1, $board['summary']['missing']);
    }

    public function test_pending_review_groups_by_class_and_section_for_selected_date_only(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $sectionB = Batch::query()->create([
            'name' => 'Class 11 JEE - B',
            'section' => 'B',
            'course_id' => $data['batch']->course_id,
            'academic_session_id' => $data['batch']->academic_session_id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);
        $sectionB->subjects()->attach([
            $data['physics']->id => ['sort_order' => 1],
        ]);
        BatchStaffAssignment::query()->create([
            'batch_id' => $sectionB->id,
            'user_id' => $data['physicsTeacher']->id,
            'role' => BatchStaffRole::SubjectTeacher,
            'course_subject_id' => $data['physics']->id,
        ]);

        $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra today',
            'description' => 'Ex 5.2',
        ]);

        $service->submit($data['physicsTeacher'], [
            'batch_id' => $sectionB->id,
            'course_subject_id' => $data['physics']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Optics today',
            'description' => 'Chapter 9',
        ]);

        $yesterdayHomework = $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->subDay()->toDateString(),
            'title' => 'Yesterday only',
            'description' => 'Revision',
        ]);

        $approved = $service->submit($data['physicsTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['physics']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Already approved',
            'description' => 'Waves',
        ]);
        $service->approve($data['admin'], $approved->id);

        $today = $service->pendingReviewForDate(now()->toDateString());

        $this->assertSame(2, $today['total']);
        $this->assertCount(1, $today['groups']);
        $this->assertSame('Class 11 JEE', $today['groups'][0]['course_name']);
        $this->assertSame(['A', 'B'], array_column($today['groups'][0]['sections'], 'section'));
        $this->assertSame($data['mathTeacher']->name, $today['groups'][0]['sections'][0]['items'][0]['teacher']);
        $this->assertSame($data['physicsTeacher']->name, $today['groups'][0]['sections'][1]['items'][0]['teacher']);

        $yesterday = $service->pendingReviewForDate(now()->subDay()->toDateString());

        $this->assertSame(1, $yesterday['total']);
        $this->assertSame($yesterdayHomework->id, $yesterday['groups'][0]['sections'][0]['items'][0]['assignment_id']);
        $this->assertSame('Yesterday only', $yesterday['groups'][0]['sections'][0]['items'][0]['title']);
    }

    public function test_review_page_shows_pending_without_picking_a_class_first(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra practice',
            'description' => 'Ex 5.2',
        ]);

        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->assertSuccessful()
            ->assertSee('Pending homework')
            ->assertSee('Class 11 JEE')
            ->assertSee('Approve pending')
            ->assertDontSee('Algebra practice')
            ->assertDontSee('Add subject')
            ->assertDontSee('No homework for today')
            ->call('toggleDeskSection', $data['batch']->id)
            ->assertSet('openBatchId', $data['batch']->id)
            ->assertSee($data['mathTeacher']->name)
            ->assertSee($data['physicsTeacher']->name)
            ->assertSee('Algebra practice')
            ->assertSee('Physics')
            ->assertSee('No homework')
            ->assertSee('Remove')
            ->call('openClass', $data['batch']->id)
            ->assertSet('data.batch_id', $data['batch']->id)
            ->assertSee('Submitted: 1');
    }

    public function test_review_page_shows_empty_today_and_loads_past_date_pending(): void
    {
        $data = $this->seedClass();
        $yesterday = now()->subDay()->toDateString();

        app(HomeworkSubmissionService::class)->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => $yesterday,
            'title' => 'Past algebra',
            'description' => 'Revision worksheet',
        ]);

        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->assertSuccessful()
            ->assertSee('No homework for today')
            ->assertDontSee('Past algebra')
            ->set('data.homework_date', $yesterday)
            ->call('toggleDeskSection', $data['batch']->id)
            ->assertSee($data['mathTeacher']->name)
            ->assertSee('Past algebra')
            ->assertDontSee('No homework for today');
    }

    public function test_desk_lists_every_active_section_including_empty(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        Batch::query()->create([
            'name' => 'Class 11 JEE - B',
            'section' => 'B',
            'course_id' => $data['batch']->course_id,
            'academic_session_id' => $data['batch']->academic_session_id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        $biology = CourseSubject::query()->create([
            'course_id' => $data['batch']->course_id,
            'name' => 'Biology',
            'code' => 'BIO',
            'default_max_marks' => 100,
            'sort_order' => 3,
            'is_active' => true,
        ]);
        $data['batch']->subjects()->attach($biology->id, ['sort_order' => 3]);

        $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra today',
            'description' => 'Ex 5.2',
        ]);

        $desk = $service->deskForDate(now()->toDateString());

        $this->assertSame(1, $desk['counts']['waiting']);
        $this->assertSame(0, $desk['counts']['ready']);
        $this->assertSame(1, $desk['counts']['empty']);
        $this->assertSame('Class 11 JEE', $desk['groups'][0]['course_name']);
        $this->assertSame(['A', 'B'], array_column($desk['groups'][0]['sections'], 'section'));
        $this->assertSame(1, $desk['groups'][0]['sections'][0]['priority']);
        $this->assertSame(4, $desk['groups'][0]['sections'][1]['priority']);

        $sectionA = collect($desk['groups'][0]['sections'][0]['items'])->keyBy('course_subject_id');
        $this->assertCount(3, $sectionA);
        $this->assertSame($data['mathTeacher']->name, $sectionA[$data['maths']->id]['teacher']);
        $this->assertSame('Algebra today', $sectionA[$data['maths']->id]['title']);
        $this->assertSame('submitted', $sectionA[$data['maths']->id]['status_key']);
        $this->assertSame($data['physicsTeacher']->name, $sectionA[$data['physics']->id]['teacher']);
        $this->assertSame('', $sectionA[$data['physics']->id]['title']);
        $this->assertNull($sectionA[$data['physics']->id]['status_key']);
        $this->assertSame('', $sectionA[$biology->id]['teacher']);
        $this->assertSame('', $sectionA[$biology->id]['title']);
        $this->assertSame([], $desk['groups'][0]['sections'][1]['items']);
    }

    public function test_desk_accordion_opens_one_class_at_a_time(): void
    {
        $data = $this->seedClass();
        $other = Batch::query()->create([
            'name' => 'Class 11 JEE - B',
            'section' => 'B',
            'course_id' => $data['batch']->course_id,
            'academic_session_id' => $data['batch']->academic_session_id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        app(HomeworkSubmissionService::class)->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra practice',
            'description' => 'Ex 5.2',
        ]);

        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->assertDontSee('Algebra practice')
            ->call('toggleDeskSection', $data['batch']->id)
            ->assertSet('openBatchId', $data['batch']->id)
            ->assertSee('Algebra practice')
            ->call('toggleDeskSection', $other->id)
            ->assertSet('openBatchId', $other->id)
            ->assertDontSee('Algebra practice')
            ->call('toggleDeskSection', $other->id)
            ->assertSet('openBatchId', null);
    }

    public function test_homework_menu_sends_admin_to_the_desk(): void
    {
        $data = $this->seedClass();
        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkPage::class)
            ->assertRedirect(HomeworkReviewPage::getUrl());
    }

    public function test_homework_menu_sends_teacher_to_their_desk(): void
    {
        $data = $this->seedClass();
        $this->actingAs($data['mathTeacher']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkPage::class)
            ->assertRedirect(SubmitHomeworkPage::getUrl());
    }

    public function test_teacher_desk_lists_only_assigned_subjects(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra today',
            'description' => 'Ex 5.2',
        ]);

        $desk = $service->teacherDeskForDate($data['mathTeacher'], now()->toDateString());

        $this->assertSame(0, $desk['counts']['missing']);
        $this->assertSame(1, $desk['counts']['submitted']);
        $this->assertCount(1, $desk['groups'][0]['sections'][0]['subjects']);
        $this->assertSame($data['maths']->id, $desk['groups'][0]['sections'][0]['subjects'][0]['course_subject_id']);
        $this->assertSame('submitted', $desk['groups'][0]['sections'][0]['subjects'][0]['status_key']);

        $physicsDesk = $service->teacherDeskForDate($data['physicsTeacher'], now()->toDateString());

        $this->assertSame(1, $physicsDesk['counts']['missing']);
        $this->assertSame(0, $physicsDesk['counts']['submitted']);
        $this->assertSame($data['physics']->id, $physicsDesk['groups'][0]['sections'][0]['subjects'][0]['course_subject_id']);
        $this->assertNull($physicsDesk['groups'][0]['sections'][0]['subjects'][0]['assignment_id']);
    }

    public function test_teacher_desk_page_shows_assigned_class_without_picking_first(): void
    {
        $data = $this->seedClass();
        $this->actingAs($data['mathTeacher']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SubmitHomeworkPage::class)
            ->assertSuccessful()
            ->assertSee('Section A')
            ->assertSee('Mathematics')
            ->assertSee('Add homework')
            ->assertDontSee('Check completion')
            ->assertDontSee('Review & send')
            ->call('startAdd', $data['batch']->id, $data['maths']->id)
            ->assertSet('data.batch_id', $data['batch']->id)
            ->assertSet('data.course_subject_id', $data['maths']->id)
            ->set('data.description', 'Complete exercise 5.2')
            ->call('submit');

        $assignment = HomeworkAssignment::query()
            ->where('course_subject_id', $data['maths']->id)
            ->first();

        $this->assertNotNull($assignment);
        $this->assertSame(HomeworkAssignmentStatus::Submitted, $assignment->status);
        $this->assertSame($data['mathTeacher']->id, $assignment->submitted_by_user_id);
        $this->assertNull($assignment->approved_by_user_id);
    }

    public function test_teacher_can_check_completion_from_their_desk_after_submit(): void
    {
        $data = $this->seedClass();
        $this->actingAs($data['mathTeacher']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertTrue(HomeworkCheckPage::canAccess());
        $this->assertFalse(HomeworkReviewPage::canAccess());

        Livewire::test(SubmitHomeworkPage::class)
            ->assertDontSee('Check completion')
            ->call('startAdd', $data['batch']->id, $data['maths']->id)
            ->set('data.description', 'Complete exercise 5.2')
            ->call('submit')
            ->assertSee('Check completion');

        Livewire::withQueryParams([
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'check_date' => now()->toDateString(),
        ])->test(HomeworkCheckPage::class)
            ->assertSuccessful()
            ->assertSet('data.batch_id', $data['batch']->id)
            ->assertSet('data.course_subject_id', $data['maths']->id);
    }

    public function test_teacher_check_does_not_open_unassigned_subject(): void
    {
        $data = $this->seedClass();
        $this->actingAs($data['mathTeacher']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::withQueryParams([
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['physics']->id,
            'check_date' => now()->toDateString(),
        ])->test(HomeworkCheckPage::class)
            ->assertSuccessful()
            ->assertSet('data.batch_id', $data['batch']->id)
            ->assertSet('data.course_subject_id', $data['maths']->id)
            ->assertNotSet('data.course_subject_id', $data['physics']->id);
    }

    public function test_teacher_without_class_cannot_open_homework_check(): void
    {
        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->assignRole(StaffJobRole::Teacher->value);
        $this->actingAs($teacher);

        $this->assertFalse(HomeworkCheckPage::canAccess());
    }

    public function test_teacher_cannot_mark_unassigned_subject(): void
    {
        $data = $this->seedClass();
        $student = Student::query()->first();

        $this->expectException(ValidationException::class);

        app(HomeworkCheckService::class)->mark(
            $data['mathTeacher'],
            $data['batch']->id,
            $student->id,
            $data['physics']->id,
            'Topic',
            HomeworkCheckStatus::Done,
        );
    }

    public function test_coordinator_approves_and_sends_from_the_desk(): void
    {
        $sequence = 0;

        Http::fake([
            'https://graph.facebook.com/*' => function () use (&$sequence) {
                $sequence++;

                return Http::response([
                    'messages' => [['id' => 'wamid.DESK'.$sequence]],
                ], 200);
            },
        ]);

        $data = $this->seedClass();
        $this->seedCombinedTemplate();
        $service = app(HomeworkSubmissionService::class);

        $maths = $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra',
            'description' => 'Ex 5.2',
        ]);

        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->assertDontSee('Add subject')
            ->call('approvePending', $data['batch']->id)
            ->assertSet('data.batch_id', $data['batch']->id)
            ->assertSee('Send to parents')
            ->assertDontSee('Resend')
            ->call('sendCombinedForBatch', $data['batch']->id)
            ->assertSee('Resend')
            ->assertDontSee('Remove');

        $maths->refresh();

        $this->assertSame(HomeworkAssignmentStatus::Sent, $maths->status);
        $this->assertSame($data['admin']->id, $maths->approved_by_user_id);
        $this->assertSame($data['admin']->id, $maths->combined_sent_by_user_id);
    }

    public function test_coordinator_can_remove_waiting_homework_from_the_open_class(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $maths = $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra',
            'description' => 'Ex 5.2',
        ]);

        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->assertDontSee('Remove')
            ->call('toggleDeskSection', $data['batch']->id)
            ->assertSee('Remove')
            ->call('remove', $maths->id)
            ->assertDontSee('Algebra');

        $this->assertNull(HomeworkAssignment::query()->find($maths->id));
    }

    public function test_combined_send_only_covers_approved_subjects(): void
    {
        $sequence = 0;

        // Meta returns a unique wamid per message; meta_whatsapp_messages.wamid is unique.
        Http::fake([
            'https://graph.facebook.com/*' => function () use (&$sequence) {
                $sequence++;

                return Http::response([
                    'messages' => [['id' => 'wamid.HWC'.$sequence]],
                ], 200);
            },
        ]);

        $data = $this->seedClass();
        $this->seedCombinedTemplate();
        $service = app(HomeworkSubmissionService::class);

        // Maths submitted then approved; Physics still only submitted (should be excluded).
        $maths = $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra',
            'description' => 'Ex 5.2',
        ]);
        $service->approve($data['admin'], $maths->id);

        $service->submit($data['physicsTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['physics']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Physics',
            'description' => 'Optics',
        ]);

        $result = $service->combinedSend($data['admin'], $data['batch']->id, now()->toDateString());

        $this->assertSame(2, $result['sent'], (string) ($result['error'] ?? ''));
        $this->assertSame(1, $result['subjects']);
        $this->assertEqualsCanonicalizing(
            ['9876500001', '9876500002'],
            collect($result['recipients'])->pluck('phone')->all(),
        );
        $this->assertSame(['sent', 'sent'], collect($result['recipients'])->pluck('status')->all());
        $this->assertSame(
            round((float) $result['unit_cost'] * 2, 4),
            $result['estimated_total_cost'],
        );
        $this->assertSame(
            WhatsAppMessageSource::Homework->value,
            MetaWhatsAppMessage::query()->firstOrFail()->message_source,
        );

        $this->assertSame(
            HomeworkAssignmentStatus::Sent,
            HomeworkAssignment::query()->find($maths->id)->status,
        );
        $this->assertSame(
            $data['admin']->id,
            HomeworkAssignment::query()->find($maths->id)->combined_sent_by_user_id,
        );
        $this->assertSame(
            WhatsAppSendActor::Staff,
            MetaWhatsAppMessage::query()->firstOrFail()->send_actor,
        );
        $this->assertSame(
            $data['admin']->id,
            MetaWhatsAppMessage::query()->firstOrFail()->sent_by_user_id,
        );
        $this->assertSame(
            HomeworkAssignmentStatus::Submitted,
            HomeworkAssignment::query()
                ->where('course_subject_id', $data['physics']->id)
                ->first()->status,
        );
    }

    public function test_combined_send_without_approved_returns_error(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra',
            'description' => 'Ex 5.2',
        ]);

        $result = $service->combinedSend($data['admin'], $data['batch']->id, now()->toDateString());

        $this->assertSame(0, $result['sent']);
        $this->assertNotNull($result['error']);
    }

    /**
     * @return array{
     *     admin: User,
     *     mathTeacher: User,
     *     physicsTeacher: User,
     *     batch: Batch,
     *     maths: CourseSubject,
     *     physics: CourseSubject
     * }
     */
    protected function seedClass(): array
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $mathTeacher = User::factory()->create(['is_active' => true]);
        $mathTeacher->assignRole(StaffJobRole::Teacher->value);

        $physicsTeacher = User::factory()->create(['is_active' => true]);
        $physicsTeacher->assignRole(StaffJobRole::Teacher->value);

        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 11 JEE',
            'code' => 'CLS-11-JEE',
            'programme_category' => 'coaching',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $maths = CourseSubject::query()->create([
            'course_id' => $course->id,
            'name' => 'Mathematics',
            'code' => 'MATH',
            'default_max_marks' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $physics = CourseSubject::query()->create([
            'course_id' => $course->id,
            'name' => 'Physics',
            'code' => 'PHY',
            'default_max_marks' => 100,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $batch = Batch::query()->create([
            'name' => 'Class 11 JEE - A',
            'section' => 'A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        $batch->subjects()->attach([
            $maths->id => ['sort_order' => 1],
            $physics->id => ['sort_order' => 2],
        ]);

        BatchStaffAssignment::query()->create([
            'batch_id' => $batch->id,
            'user_id' => $mathTeacher->id,
            'role' => BatchStaffRole::SubjectTeacher,
            'course_subject_id' => $maths->id,
        ]);

        BatchStaffAssignment::query()->create([
            'batch_id' => $batch->id,
            'user_id' => $physicsTeacher->id,
            'role' => BatchStaffRole::SubjectTeacher,
            'course_subject_id' => $physics->id,
        ]);

        foreach (['Riya Sharma' => '9876500001', 'Aman Verma' => '9876500002'] as $name => $mobile) {
            $student = Student::query()->create([
                'name' => $name,
                'mobile' => $mobile,
                'status' => StudentStatus::Enrolled,
            ]);

            BatchStudent::query()->create([
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'is_active' => true,
                'assigned_at' => now(),
                'assigned_by_user_id' => $admin->id,
            ]);
        }

        return compact('admin', 'mathTeacher', 'physicsTeacher', 'batch', 'maths', 'physics');
    }

    protected function seedCombinedTemplate(): void
    {
        MetaWhatsAppTemplate::query()->create([
            'name' => CombinedHomeworkWhatsAppTemplate::NAME,
            'language' => 'en',
            'status' => 'APPROVED',
            'param_count' => 4,
            'body' => CombinedHomeworkWhatsAppTemplate::BODY,
            'is_active' => true,
            'synced_at' => now(),
        ]);
    }
}
