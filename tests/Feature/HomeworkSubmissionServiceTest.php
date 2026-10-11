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
use App\Filament\Pages\MyTeachingAssignmentsPage;
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
use App\Models\HomeworkStudentLink;
use App\Models\MetaWhatsAppMessage;
use App\Models\ParentMessageSend;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\CrmPermissionSyncService;
use App\Services\HomeworkCheckService;
use App\Filament\Pages\ParentMessageSendsPage;
use App\Services\HomeworkSubmissionService;
use App\Services\ParentMessageSendService;
use App\Support\CrmAccess;
use App\Services\MetaWhatsAppCostEstimator;
use App\Support\BulkSendGuard;
use App\Support\CombinedHomeworkWhatsAppTemplate;
use Carbon\Carbon;
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

        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'Asia/Kolkata'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
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

    public function test_only_the_admin_role_can_see_whatsapp_message_cost(): void
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $coordinator = User::factory()->create(['is_active' => true]);
        $coordinator->assignRole(RoleName::Staff->value);

        $this->assertTrue(CrmAccess::canSeeMessageCost($admin));
        $this->assertFalse(CrmAccess::canSeeMessageCost($coordinator));
        $this->assertFalse(CrmAccess::canSeeMessageCost(null));
    }

    public function test_review_page_renders_with_and_without_send_summary(): void
    {
        $data = $this->seedClass();

        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->assertSuccessful()
            ->assertDontSee('>Submit</a>', escape: false)
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

        $this->get($assignment->publicUrl())
            ->assertOk()
            ->assertSee('Algebra practice')
            ->assertSee('Complete exercise 5.2');
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

    public function test_admin_edit_keeps_a_teacher_submission_waiting(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $submitted = $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra',
            'description' => 'Exercise 1',
        ]);

        $updated = $service->submit($data['admin'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra corrected',
            'description' => 'Exercise 1 and 2',
        ], asAdmin: true);

        $this->assertSame($submitted->id, $updated->id);
        $this->assertSame(HomeworkAssignmentStatus::Submitted, $updated->status);
        $this->assertSame('Algebra corrected', $updated->title);
        $this->assertSame('Exercise 1 and 2', $updated->description);
        $this->assertNull($updated->approved_at);
        $this->assertSame($data['mathTeacher']->id, $updated->submitted_by_user_id);
    }

    public function test_admin_edit_of_approved_homework_stays_ready_to_send(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $submitted = $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra',
            'description' => 'Exercise 1',
        ]);
        $service->approve($data['admin'], (int) $submitted->id);

        $updated = $service->submit($data['admin'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra',
            'description' => 'Exercise 1 revised',
        ], asAdmin: true);

        $this->assertSame(HomeworkAssignmentStatus::Approved, $updated->status);
        $this->assertSame('Exercise 1 revised', $updated->description);
        $this->assertSame($data['admin']->id, $updated->approved_by_user_id);
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

        $desk = $service->deskForDate(now()->toDateString());
        $physicsRow = collect($desk['groups'][0]['sections'][0]['items'])
            ->firstWhere('course_subject_id', $data['physics']->id);

        $this->assertSame('Khushi Coordinator', $physicsRow['submitted_by']);
        $this->assertSame('Khushi Coordinator', $physicsRow['teacher']);

        Livewire::test(HomeworkReviewPage::class)
            ->call('toggleDeskSection', $data['batch']->id)
            ->assertSee('Khushi Coordinator')
            ->assertDontSee('Added by');
    }

    public function test_coordinator_adds_homework_from_review_desk_popup(): void
    {
        $data = $this->seedClass();
        app(CrmPermissionSyncService::class)->sync();

        $coordinator = User::factory()->create(['is_active' => true, 'name' => 'Khushi Coordinator']);
        $coordinator->syncRoles([RoleName::Staff->value, StaffJobRole::AcademicCoordinator->value]);

        $this->actingAs($coordinator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->call('toggleDeskSection', $data['batch']->id)
            ->assertSee('Add homework')
            ->call('startAdd', $data['batch']->id, $data['physics']->id)
            ->assertActionMounted('addHomework')
            ->setActionData(['description' => 'Chapter 3 numericals'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
            ->assertNotified();

        $assignment = HomeworkAssignment::query()
            ->where('course_subject_id', $data['physics']->id)
            ->first();

        $this->assertNotNull($assignment);
        $this->assertSame(HomeworkAssignmentStatus::Approved, $assignment->status);
        $this->assertSame($coordinator->id, $assignment->approved_by_user_id);
    }

    public function test_admin_adds_homework_from_review_desk_popup(): void
    {
        $data = $this->seedClass();

        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->call('toggleDeskSection', $data['batch']->id)
            ->call('startAdd', $data['batch']->id, $data['maths']->id)
            ->assertActionMounted('addHomework')
            ->setActionData(['description' => 'Algebra worksheet'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

        $assignment = HomeworkAssignment::query()
            ->where('course_subject_id', $data['maths']->id)
            ->first();

        $this->assertNotNull($assignment);
        $this->assertSame(HomeworkAssignmentStatus::Approved, $assignment->status);
        $this->assertSame($data['admin']->id, $assignment->approved_by_user_id);
    }

    public function test_submit_page_is_only_for_teachers(): void
    {
        $data = $this->seedClass();
        app(CrmPermissionSyncService::class)->sync();

        $coordinator = User::factory()->create(['is_active' => true, 'name' => 'Khushi Coordinator']);
        $coordinator->syncRoles([RoleName::Staff->value, StaffJobRole::AcademicCoordinator->value]);

        $this->actingAs($data['admin']);
        $this->assertFalse(SubmitHomeworkPage::canAccess());
        $this->assertTrue(HomeworkReviewPage::canAccess());

        $this->actingAs($coordinator);
        $this->assertFalse(SubmitHomeworkPage::canAccess());
        $this->assertTrue(HomeworkReviewPage::canAccess());

        $this->actingAs($data['mathTeacher']);
        $this->assertTrue(SubmitHomeworkPage::canAccess());
        $this->assertFalse(HomeworkReviewPage::canAccess());
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

        $yesterdayHomework = $this->submitWhileThatMorningWasOpen($data['mathTeacher'], [
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
            ->assertSee('Classes')
            ->assertSee('Class 11 JEE')
            ->assertSee('1 to check')
            ->assertDontSee('Approve pending')
            ->assertDontSee('Algebra practice')
            ->assertDontSee('Add subject')
            ->assertDontSee('No homework for today')
            ->call('toggleDeskSection', $data['batch']->id)
            ->assertSet('openBatchId', $data['batch']->id)
            ->assertSee($data['mathTeacher']->name)
            ->assertSee($data['physicsTeacher']->name)
            ->assertSee('Algebra practice')
            ->assertSee('Ex 5.2')
            ->assertSee('Open homework')
            ->assertSee('Physics')
            ->assertSee('No homework')
            ->assertSee('Remove')
            ->assertDontSee('Added by')
            ->call('openClass', $data['batch']->id)
            ->assertSet('data.batch_id', $data['batch']->id)
            ->assertSee('Submitted: 1');
    }

    public function test_review_page_shows_empty_today_and_loads_past_date_pending(): void
    {
        $data = $this->seedClass();
        $yesterday = now()->subDay()->toDateString();

        $this->submitWhileThatMorningWasOpen($data['mathTeacher'], [
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
            ->assertSee('2 subjects · none yet')
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
        $this->assertSame(5, $desk['groups'][0]['sections'][1]['priority']);

        $sectionA = collect($desk['groups'][0]['sections'][0]['items'])->keyBy('course_subject_id');
        $this->assertCount(3, $sectionA);
        $this->assertSame($data['mathTeacher']->name, $sectionA[$data['maths']->id]['teacher']);
        $this->assertSame($data['mathTeacher']->name, $sectionA[$data['maths']->id]['submitted_by']);
        $this->assertSame('Algebra today', $sectionA[$data['maths']->id]['title']);
        $this->assertSame('submitted', $sectionA[$data['maths']->id]['status_key']);
        $this->assertNotEmpty($sectionA[$data['maths']->id]['public_url']);
        $this->assertSame('Ex 5.2', $sectionA[$data['maths']->id]['description']);
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

    public function test_two_teachers_on_one_subject_each_keep_their_own_homework(): void
    {
        $data = $this->seedClass();
        $secondTeacher = User::factory()->create([
            'name' => 'Atul Sir',
            'is_active' => true,
        ]);
        $secondTeacher->assignRole(StaffJobRole::Teacher->value);
        BatchStaffAssignment::query()->create([
            'batch_id' => $data['batch']->id,
            'user_id' => $secondTeacher->id,
            'role' => BatchStaffRole::SubjectTeacher,
            'course_subject_id' => $data['maths']->id,
        ]);

        $service = app(HomeworkSubmissionService::class);
        $first = $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra',
            'description' => 'Ex 5.2',
        ]);
        $service->approve($data['admin'], (int) $first->id);

        $second = $service->submit($secondTeacher, [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Geometry',
            'description' => 'Ex 6.1',
        ]);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('Ex 5.2', $first->fresh()->description);
        $this->assertSame(HomeworkAssignmentStatus::Approved, $first->fresh()->status);
        $this->assertSame('Ex 6.1', $second->description);
        $this->assertSame($secondTeacher->id, $second->submitted_by_user_id);

        $firstDesk = $service->teacherDeskForDate($data['mathTeacher'], now()->toDateString());
        $this->assertSame($first->id, $firstDesk['groups'][0]['sections'][0]['subjects'][0]['assignment_id']);
        $this->assertSame('approved', $firstDesk['groups'][0]['sections'][0]['subjects'][0]['status_key']);

        $secondDesk = $service->teacherDeskForDate($secondTeacher, now()->toDateString());
        $this->assertSame($second->id, $secondDesk['groups'][0]['sections'][0]['subjects'][0]['assignment_id']);
        $this->assertSame('submitted', $secondDesk['groups'][0]['sections'][0]['subjects'][0]['status_key']);

        $review = $service->deskForDate(now()->toDateString());
        $mathItems = collect($review['groups'][0]['sections'][0]['items'])
            ->where('course_subject_id', $data['maths']->id)
            ->values();
        $this->assertCount(2, $mathItems);
        $this->assertEqualsCanonicalizing(
            [$data['mathTeacher']->name, $secondTeacher->name],
            $mathItems->pluck('submitted_by')->all(),
        );
        $this->assertEqualsCanonicalizing(
            [$data['mathTeacher']->name, $secondTeacher->name],
            $mathItems->pluck('teacher')->all(),
        );
    }

    public function test_desk_shows_a_pending_line_for_the_teacher_who_has_not_given_homework(): void
    {
        $data = $this->seedClass();
        $secondTeacher = User::factory()->create([
            'name' => 'Umakanth Sir',
            'is_active' => true,
        ]);
        $secondTeacher->assignRole(StaffJobRole::Teacher->value);
        BatchStaffAssignment::query()->create([
            'batch_id' => $data['batch']->id,
            'user_id' => $secondTeacher->id,
            'role' => BatchStaffRole::SubjectTeacher,
            'course_subject_id' => $data['maths']->id,
        ]);

        $service = app(HomeworkSubmissionService::class);
        $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra',
            'description' => 'Ex 5.2',
        ]);

        $mathItems = collect($service->deskForDate(now()->toDateString())['groups'][0]['sections'][0]['items'])
            ->where('course_subject_id', $data['maths']->id)
            ->values();

        $this->assertCount(2, $mathItems);

        $given = $mathItems->first(fn (array $item): bool => $item['status_key'] === 'submitted');
        $pending = $mathItems->first(fn (array $item): bool => $item['status_key'] === null);

        $this->assertSame($data['mathTeacher']->name, $given['teacher']);
        $this->assertSame($data['mathTeacher']->name, $given['submitted_by']);
        $this->assertNotNull($given['assignment_id']);
        $this->assertSame('Umakanth Sir', $pending['teacher']);
        $this->assertSame('', $pending['submitted_by']);
        $this->assertNull($pending['assignment_id']);
        $this->assertSame('Mathematics', $pending['subject']);
    }

    public function test_teacher_desk_page_shows_assigned_class_without_picking_first(): void
    {
        $data = $this->seedClass();
        $this->actingAs($data['mathTeacher']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(MyTeachingAssignmentsPage::class)
            ->assertSuccessful()
            ->assertSee('Give homework')
            ->assertSee('batch_id='.$data['batch']->id, false)
            ->assertSee('course_subject_id='.$data['maths']->id, false);

        Livewire::withQueryParams([
            'batch_id' => (string) $data['batch']->id,
            'course_subject_id' => (string) $data['maths']->id,
        ])->test(SubmitHomeworkPage::class)
            ->assertActionMounted('addHomework');

        Livewire::withQueryParams([]);

        Livewire::test(SubmitHomeworkPage::class)
            ->assertSuccessful()
            ->assertSee('Section A')
            ->assertSee('Mathematics')
            ->assertSee('Add homework')
            ->assertDontSee('Check completion')
            ->assertDontSee('Review & send')
            ->assertDontSee('Use this if Add homework did not open the form.')
            ->call('startAdd', $data['batch']->id, $data['maths']->id)
            ->assertActionMounted('addHomework')
            ->setActionData(['description' => 'Complete exercise 5.2'])
            ->callMountedAction()
            ->assertHasNoActionErrors();

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
            ->assertActionMounted('addHomework')
            ->setActionData(['description' => 'Complete exercise 5.2'])
            ->callMountedAction()
            ->assertHasNoActionErrors()
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
            ->assertSee('1 to check')
            ->assertDontSee('Approve pending')
            ->call('toggleDeskSection', $data['batch']->id)
            ->assertSee('Open homework')
            ->call('approve', $maths->id)
            ->assertSee('Send to parents')
            ->assertSee('Not submitted: Physics')
            ->assertDontSee('Resend')
            ->call('sendCombinedForBatch', $data['batch']->id)
            ->assertSee('A teacher has not given homework')
            ->assertSee('Teacher was absent')
            ->assertDontSee('Resend')
            ->call('confirmClosedSend')
            ->assertNotified('Choose what happened for Physics.')
            ->call('setMissingReason', $data['physics']->id, 'no_homework')
            ->call('confirmNoHomeworkNote', $data['physics']->id)
            ->assertNotified('Write why there is no homework')
            ->set('missingSubjectNotes.'.$data['physics']->id, 'Chapter was already finished in class.')
            ->call('confirmNoHomeworkNote', $data['physics']->id)
            ->call('confirmClosedSend')
            ->assertSee('Please wait 5 min')
            ->assertDontSee('Remove');

        $maths->refresh();

        $this->assertSame(HomeworkAssignmentStatus::Sent, $maths->status);

        try {
            $service->submit($data['physicsTeacher'], [
                'batch_id' => $data['batch']->id,
                'course_subject_id' => $data['physics']->id,
                'homework_date' => now()->toDateString(),
                'title' => 'Too late',
                'description' => 'Optics',
            ]);
            $this->fail('A closed subject still accepted homework.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This subject is closed for the day. Parents were already sent the message, so homework cannot be added now.',
                $exception->errors()['homework_date'][0],
            );
        }
        $this->assertSame($data['admin']->id, $maths->approved_by_user_id);
        $this->assertSame($data['admin']->id, $maths->combined_sent_by_user_id);
        $this->assertDatabaseHas('homework_subject_closures', [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['physics']->id,
            'reason' => 'no_homework',
            'reason_note' => 'Chapter was already finished in class.',
        ]);
    }

    public function test_send_to_parents_warns_and_does_not_send_while_homework_is_waiting(): void
    {
        Http::fake();

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
        $service->approve($data['admin'], $maths->id);

        $service->submit($data['physicsTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['physics']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Optics',
            'description' => 'Chapter 9',
        ]);

        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->call('sendCombinedForBatch', $data['batch']->id)
            ->assertNotified('Homework is still waiting to be checked')
            ->assertDontSee('A teacher has not given homework');

        $this->assertSame(HomeworkAssignmentStatus::Approved, $maths->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_send_to_parents_asks_when_another_teacher_on_the_subject_has_not_given_homework(): void
    {
        Http::fake();

        $data = $this->seedClass();
        $this->seedCombinedTemplate();
        $service = app(HomeworkSubmissionService::class);

        foreach (['maths' => $data['mathTeacher'], 'physics' => $data['physicsTeacher']] as $subjectKey => $teacher) {
            $assignment = $service->submit($teacher, [
                'batch_id' => $data['batch']->id,
                'course_subject_id' => $data[$subjectKey]->id,
                'homework_date' => now()->toDateString(),
                'title' => 'Ready',
                'description' => 'Done',
            ]);
            $service->approve($data['admin'], $assignment->id);
        }

        $otherPhysicsTeacher = User::factory()->create([
            'name' => 'Extra Physics Teacher',
            'is_active' => true,
        ]);
        $otherPhysicsTeacher->assignRole(StaffJobRole::Teacher->value);

        BatchStaffAssignment::query()->create([
            'batch_id' => $data['batch']->id,
            'user_id' => $otherPhysicsTeacher->id,
            'role' => BatchStaffRole::SubjectTeacher,
            'course_subject_id' => $data['physics']->id,
        ]);

        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->call('sendCombinedForBatch', $data['batch']->id)
            ->assertSee('A teacher has not given homework')
            ->assertSee('Extra Physics Teacher');

        Http::assertNothingSent();
    }

    public function test_approving_one_subject_does_not_approve_the_other(): void
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
        $physics = $service->submit($data['physicsTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['physics']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Optics',
            'description' => 'Chapter 9',
        ]);

        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->assertSee('2 to check')
            ->assertDontSee('Approve pending')
            ->call('toggleDeskSection', $data['batch']->id)
            ->assertSee('Open homework')
            ->call('approve', $maths->id);

        $this->assertSame(HomeworkAssignmentStatus::Approved, $maths->fresh()->status);
        $this->assertSame(HomeworkAssignmentStatus::Submitted, $physics->fresh()->status);
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

    public function test_combined_send_puts_each_subject_on_its_own_line_when_that_template_is_approved(): void
    {
        $sequence = 0;

        Http::fake([
            'https://graph.facebook.com/*' => function () use (&$sequence) {
                $sequence++;

                return Http::response([
                    'messages' => [['id' => 'wamid.LINE'.$sequence]],
                ], 200);
            },
        ]);

        $data = $this->seedClass();
        $this->seedCombinedTemplate();
        MetaWhatsAppTemplate::query()
            ->where('name', CombinedHomeworkWhatsAppTemplate::NAME)
            ->update([
                'param_count' => 3 + CombinedHomeworkWhatsAppTemplate::SUBJECT_SLOTS,
                'body' => CombinedHomeworkWhatsAppTemplate::BODY,
            ]);

        $service = app(HomeworkSubmissionService::class);

        foreach (['maths' => $data['mathTeacher'], 'physics' => $data['physicsTeacher']] as $subject => $teacher) {
            $assignment = $service->submit($teacher, [
                'batch_id' => $data['batch']->id,
                'course_subject_id' => $data[$subject]->id,
                'homework_date' => now()->toDateString(),
                'title' => $subject,
                'description' => 'Today',
            ]);
            $service->approve($data['admin'], $assignment->id);
        }

        $result = $service->combinedSend($data['admin'], $data['batch']->id, now()->toDateString());

        $this->assertSame(2, $result['sent'], (string) ($result['error'] ?? ''));
        $this->assertSame(CombinedHomeworkWhatsAppTemplate::NAME, $result['template']);

        $bodies = MetaWhatsAppMessage::query()->pluck('body_preview');
        $this->assertCount(2, $bodies);
        $this->assertTrue($bodies->every(function (string $body): bool {
            return str_contains($body, "Mathematics")
                && str_contains($body, "Physics")
                && ! str_contains($body, ' | ');
        }));
    }

    public function test_combined_send_gives_each_student_a_unique_link_and_reuses_it_on_resend(): void
    {
        $sequence = 0;

        Http::fake([
            'https://graph.facebook.com/*' => function () use (&$sequence) {
                $sequence++;

                return Http::response([
                    'messages' => [['id' => 'wamid.UNIQ'.$sequence]],
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
        $service->approve($data['admin'], $maths->id);

        $first = $service->combinedSend($data['admin'], $data['batch']->id, now()->toDateString());

        $this->assertSame(2, $first['sent'], (string) ($first['error'] ?? ''));

        $links = HomeworkStudentLink::query()
            ->where('homework_assignment_id', $maths->id)
            ->orderBy('student_id')
            ->get();

        $this->assertCount(2, $links);
        $this->assertCount(2, $links->pluck('token')->unique());
        $this->assertNotContains($maths->public_token, $links->pluck('token')->all());

        $tokens = $links->pluck('token')->all();

        $second = $service->combinedSend($data['admin'], $data['batch']->id, now()->toDateString());

        $this->assertSame(2, $second['sent'], (string) ($second['error'] ?? ''));

        $sends = ParentMessageSend::query()->orderBy('id')->get();
        $this->assertCount(2, $sends);
        $this->assertFalse($sends[0]->is_resend);
        $this->assertTrue($sends[1]->is_resend);
        $this->assertSame($data['admin']->id, $sends[1]->sent_by_user_id);
        $this->assertSame(ParentMessageSend::Homework, $sends[0]->kind);

        $report = app(ParentMessageSendService::class)->staffReport(now()->startOfDay(), now()->endOfDay());
        $this->assertSame($data['admin']->id, $report[0]['user_id']);
        $this->assertSame(1, $report[0]['homework_sends']);
        $this->assertSame(1, $report[0]['homework_resends']);
        $this->assertSame(0, $report[0]['exam_sends']);
        $this->assertEqualsCanonicalizing(
            $tokens,
            HomeworkStudentLink::query()
                ->where('homework_assignment_id', $maths->id)
                ->pluck('token')
                ->all(),
        );
    }

    public function test_resend_warns_before_a_duplicate_homework_message(): void
    {
        $sequence = 0;

        Http::fake([
            'https://graph.facebook.com/*' => function () use (&$sequence) {
                $sequence++;

                return Http::response([
                    'messages' => [['id' => 'wamid.DUP'.$sequence]],
                ], 200);
            },
        ]);

        $data = $this->seedClass();
        $this->seedCombinedTemplate();
        $service = app(HomeworkSubmissionService::class);

        foreach (['maths' => $data['mathTeacher'], 'physics' => $data['physicsTeacher']] as $subject => $teacher) {
            $assignment = $service->submit($teacher, [
                'batch_id' => $data['batch']->id,
                'course_subject_id' => $data[$subject]->id,
                'homework_date' => now()->toDateString(),
                'title' => $subject,
                'description' => 'Today',
            ]);
            $service->approve($data['admin'], $assignment->id);
        }

        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->travelTo(now()->startOfDay()->addHours(12));

        Livewire::test(HomeworkReviewPage::class)
            ->call('sendCombinedForBatch', $data['batch']->id)
            ->assertSee('Please wait 5 min');

        $this->assertSame(1, ParentMessageSend::query()->count());

        $this->travel(6)->minutes();

        Livewire::test(HomeworkReviewPage::class)
            ->call('askDuplicateSend', $data['batch']->id)
            ->assertSee('Do not send again')
            ->assertSee('Send again anyway')
            ->assertSet('duplicateSendBatchId', $data['batch']->id)
            ->call('cancelDuplicateSend')
            ->assertSet('duplicateSendBatchId', null);

        $this->assertSame(1, ParentMessageSend::query()->count());

        Livewire::test(HomeworkReviewPage::class)
            ->call('askDuplicateSend', $data['batch']->id)
            ->call('confirmDuplicateSend');

        $sends = ParentMessageSend::query()->orderBy('id')->get();
        $this->assertCount(2, $sends);
        $this->assertTrue($sends[1]->is_resend);
        $this->assertSame($data['admin']->name, $sends[1]->sentBy->name);
    }

    public function test_the_same_class_cannot_be_sent_again_for_five_minutes(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.WAIT']],
            ], 200),
        ]);

        $this->travelTo(now()->startOfDay()->addHours(12));

        $data = $this->seedClass();
        $this->seedCombinedTemplate();
        $service = app(HomeworkSubmissionService::class);

        foreach (['maths' => $data['mathTeacher'], 'physics' => $data['physicsTeacher']] as $subject => $teacher) {
            $assignment = $service->submit($teacher, [
                'batch_id' => $data['batch']->id,
                'course_subject_id' => $data[$subject]->id,
                'homework_date' => now()->toDateString(),
                'title' => $subject,
                'description' => 'Today',
            ]);
            $service->approve($data['admin'], $assignment->id);
        }

        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->call('sendCombinedForBatch', $data['batch']->id)
            ->assertSee('Please wait 5 min');

        $this->assertSame(1, ParentMessageSend::query()->count());

        Livewire::test(HomeworkReviewPage::class)
            ->call('askDuplicateSend', $data['batch']->id)
            ->assertNotified('Please wait')
            ->assertSet('duplicateSendBatchId', null);

        $this->assertSame(1, ParentMessageSend::query()->count());
    }

    public function test_a_new_days_homework_is_a_first_send_and_a_second_click_that_day_is_a_resend(): void
    {
        $sequence = 0;

        Http::fake([
            'https://graph.facebook.com/*' => function () use (&$sequence) {
                $sequence++;

                return Http::response([
                    'messages' => [['id' => 'wamid.AGAIN'.$sequence]],
                ], 200);
            },
        ]);

        $data = $this->seedClass();
        $this->seedCombinedTemplate();

        ParentMessageSend::query()->create([
            'kind' => ParentMessageSend::Homework,
            'is_resend' => false,
            'batch_id' => $data['batch']->id,
            'homework_date' => now()->subDay()->toDateString(),
            'label' => $data['batch']->displayLabel(),
            'sent_by_user_id' => $data['admin']->id,
            'parent_count' => 2,
            'sent_at' => now()->subDay(),
        ]);

        $service = app(HomeworkSubmissionService::class);
        $maths = $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra',
            'description' => 'Ex 5.2',
        ]);
        $service->approve($data['admin'], $maths->id);
        $service->combinedSend($data['admin'], $data['batch']->id, now()->toDateString());

        $latest = ParentMessageSend::query()->orderByDesc('id')->first();
        $this->assertFalse($latest->is_resend);

        $report = app(ParentMessageSendService::class);
        $staff = $report->staffReport(now()->subDay()->startOfDay(), now()->endOfDay());
        $this->assertSame(2, $staff[0]['homework_sends']);
        $this->assertSame(0, $staff[0]['homework_resends']);

        $clicks = $report->recent(now()->subDay()->startOfDay(), now()->endOfDay());
        $this->assertSame('First send', $clicks[0]['repeat']);
        $this->assertSame('First send', $clicks[1]['repeat']);

        $service->combinedSend($data['admin'], $data['batch']->id, now()->toDateString());

        $secondToday = ParentMessageSend::query()->orderByDesc('id')->first();
        $this->assertTrue($secondToday->is_resend);

        $staff = $report->staffReport(now()->subDay()->startOfDay(), now()->endOfDay());
        $this->assertSame(2, $staff[0]['homework_sends']);
        $this->assertSame(1, $staff[0]['homework_resends']);

        $clicks = $report->recent(now()->subDay()->startOfDay(), now()->endOfDay());
        $this->assertSame('Resend', $clicks[0]['repeat']);
    }

    public function test_each_click_list_pages_through_every_send(): void
    {
        $data = $this->seedClass();
        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach (range(1, 16) as $number) {
            ParentMessageSend::query()->create([
                'kind' => ParentMessageSend::Homework,
                'is_resend' => $number !== 1,
                'batch_id' => $data['batch']->id,
                'homework_date' => now()->toDateString(),
                'label' => sprintf('Send A%02d', $number),
                'sent_by_user_id' => $data['admin']->id,
                'parent_count' => 2,
                'sent_at' => now()->subMinutes(20 - $number),
            ]);
        }

        $report = app(ParentMessageSendService::class);
        $pageOne = $report->recent(now()->startOfDay(), now()->endOfDay(), 15, 1);
        $pageTwo = $report->recent(now()->startOfDay(), now()->endOfDay(), 15, 2);

        $this->assertSame(16, $pageOne->total());
        $this->assertCount(15, $pageOne->items());
        $this->assertCount(1, $pageTwo->items());
        $this->assertSame('Send A01', $pageTwo->items()[0]['place']);
        $this->assertSame('First send', $pageTwo->items()[0]['repeat']);

        Livewire::test(ParentMessageSendsPage::class)
            ->assertSee('Send A16')
            ->assertDontSee('Send A01')
            ->call('gotoPage', 2)
            ->assertSee('Send A01')
            ->assertSee('First send');
    }

    public function test_parent_send_report_is_open_to_the_coordinator_and_closed_to_a_teacher(): void
    {
        $data = $this->seedClass();

        $this->actingAs($data['mathTeacher']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->assertFalse(ParentMessageSendsPage::canAccess());

        $coordinator = User::factory()->create(['is_active' => true]);
        $coordinator->syncRoles([RoleName::Staff->value, StaffJobRole::AcademicCoordinator->value]);

        $this->actingAs($coordinator);
        $this->assertTrue(ParentMessageSendsPage::canAccess());

        Livewire::test(ParentMessageSendsPage::class)
            ->assertSee('Homework resends')
            ->assertSee('Exam mark resends')
            ->assertDontSee('₹');
    }

    public function test_desk_shows_who_opened_unique_homework_links(): void
    {
        $sequence = 0;

        Http::fake([
            'https://graph.facebook.com/*' => function () use (&$sequence) {
                $sequence++;

                return Http::response([
                    'messages' => [['id' => 'wamid.OPEN'.$sequence]],
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
        $service->approve($data['admin'], $maths->id);

        $waitingDesk = $this->mathsDeskItem($service, $data['maths']->id);
        $this->assertSame(0, $waitingDesk['link_total']);

        $service->combinedSend($data['admin'], $data['batch']->id, now()->toDateString());

        $sentDesk = $this->mathsDeskItem($service, $data['maths']->id);
        $this->assertSame(0, $sentDesk['link_opened']);
        $this->assertSame(2, $sentDesk['link_total']);
        $this->assertSame([], $sentDesk['link_opened_people']);
        $this->assertEqualsCanonicalizing(['Aman Verma', 'Riya Sharma'], $sentDesk['link_not_opened_people']);

        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->call('toggleDeskSection', $data['batch']->id)
            ->assertSee('Opened 0 / 2')
            ->assertSee('None yet')
            ->assertSee('Aman Verma')
            ->assertSee('Riya Sharma');

        $riyaLink = HomeworkStudentLink::query()
            ->where('homework_assignment_id', $maths->id)
            ->whereHas('student', fn ($query) => $query->where('name', 'Riya Sharma'))
            ->first();

        $this->assertNotNull($riyaLink);
        $this->get($riyaLink->publicUrl())->assertOk();

        $this->get($maths->publicUrl())->assertOk();

        $afterOpen = $this->mathsDeskItem($service, $data['maths']->id);
        $this->assertSame(1, $afterOpen['link_opened']);
        $this->assertSame(2, $afterOpen['link_total']);
        $this->assertSame(['Aman Verma'], $afterOpen['link_not_opened_people']);
        $this->assertSame('Riya Sharma', $afterOpen['link_opened_people'][0]['name']);
        $this->assertNotNull($afterOpen['link_opened_people'][0]['at']);

        Livewire::test(HomeworkReviewPage::class)
            ->call('toggleDeskSection', $data['batch']->id)
            ->assertSee('Opened 1 / 2')
            ->assertSee('Riya Sharma')
            ->assertSee('Aman Verma');
    }

    public function test_teacher_desk_shows_who_opened_unique_homework_links(): void
    {
        $sequence = 0;

        Http::fake([
            'https://graph.facebook.com/*' => function () use (&$sequence) {
                $sequence++;

                return Http::response([
                    'messages' => [['id' => 'wamid.TEACH'.$sequence]],
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
        $service->approve($data['admin'], $maths->id);
        $service->combinedSend($data['admin'], $data['batch']->id, now()->toDateString());

        $mathsCard = $service->teacherDeskForDate($data['mathTeacher'], now()->toDateString())['groups'][0]['sections'][0]['subjects'][0];
        $this->assertSame(0, $mathsCard['link_opened']);
        $this->assertSame(2, $mathsCard['link_total']);

        $physicsCard = $service->teacherDeskForDate($data['physicsTeacher'], now()->toDateString())['groups'][0]['sections'][0]['subjects'][0];
        $this->assertSame(0, $physicsCard['link_total']);
        $this->assertSame($data['physics']->id, $physicsCard['course_subject_id']);

        $this->actingAs($data['mathTeacher']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SubmitHomeworkPage::class)
            ->assertSee('Opened 0 / 2')
            ->assertDontSee('Review & send');

        $riyaLink = HomeworkStudentLink::query()
            ->where('homework_assignment_id', $maths->id)
            ->whereHas('student', fn ($query) => $query->where('name', 'Riya Sharma'))
            ->first();

        $this->assertNotNull($riyaLink);
        $this->get($riyaLink->publicUrl())->assertOk();

        $afterOpen = $service->teacherDeskForDate($data['mathTeacher'], now()->toDateString())['groups'][0]['sections'][0]['subjects'][0];
        $this->assertSame(1, $afterOpen['link_opened']);
        $this->assertSame(2, $afterOpen['link_total']);

        Livewire::test(SubmitHomeworkPage::class)
            ->assertSee('Opened 1 / 2');

        Livewire::withQueryParams([
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'check_date' => now()->toDateString(),
        ])->test(HomeworkCheckPage::class)
            ->assertSee('cannot be checked today')
            ->assertDontSee('Not done');
    }

    public function test_teacher_sees_given_homework_and_cannot_change_it_after_approval_or_send(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $maths = $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra',
            'description' => 'Exercise 1',
        ]);

        $revised = $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra',
            'description' => 'Exercise 1 revised',
        ]);

        $this->assertSame('Exercise 1 revised', $revised->description);

        $service->approve($data['admin'], (int) $maths->id);

        $card = $service->teacherDeskForDate($data['mathTeacher'], now()->toDateString())['groups'][0]['sections'][0]['subjects'][0];
        $this->assertSame('Homework given · Approved by admin', $card['status_line']);
        $this->assertTrue($card['locked']);
        $this->assertFalse($card['can_remove']);

        try {
            $service->submit($data['mathTeacher'], [
                'batch_id' => $data['batch']->id,
                'course_subject_id' => $data['maths']->id,
                'homework_date' => now()->toDateString(),
                'title' => 'Algebra',
                'description' => 'Should not save',
            ]);
            $this->fail('Approved homework was changed by the teacher.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This homework is approved by admin. You cannot change it.',
                $exception->errors()['homework_date'][0],
            );
        }

        try {
            $service->deleteSubmission($data['mathTeacher'], (int) $maths->id);
            $this->fail('Approved homework was removed by the teacher.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This homework is approved by admin. You cannot change it.',
                $exception->errors()['delete'][0],
            );
        }

        $this->actingAs($data['mathTeacher']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SubmitHomeworkPage::class)
            ->assertSee('Homework given · Approved by admin')
            ->assertSee('You cannot change this.')
            ->assertDontSee('Update')
            ->call('startAdd', $data['batch']->id, $data['maths']->id)
            ->assertNotified('This homework is approved by admin. You cannot change it.');

        $maths->forceFill(['status' => HomeworkAssignmentStatus::Sent])->save();

        try {
            $service->submit($data['mathTeacher'], [
                'batch_id' => $data['batch']->id,
                'course_subject_id' => $data['maths']->id,
                'homework_date' => now()->toDateString(),
                'title' => 'Algebra',
                'description' => 'Should not save',
            ]);
            $this->fail('Sent homework was changed by the teacher.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This homework was sent to parents. You cannot change it.',
                $exception->errors()['homework_date'][0],
            );
        }

        Livewire::test(SubmitHomeworkPage::class)
            ->assertSee('Homework given · Sent to parents')
            ->assertDontSee('Update')
            ->assertDontSee('Remove');
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
     *     course_subject_id: int,
     *     link_opened: int,
     *     link_total: int,
     *     link_opened_people: list<array{name: string, at: ?string}>,
     *     link_not_opened_people: list<string>
     * }
     */
    protected function mathsDeskItem(HomeworkSubmissionService $service, int $mathsId): array
    {
        $desk = $service->deskForDate(now()->toDateString());
        $items = collect($desk['groups'][0]['sections'][0]['items'])->keyBy('course_subject_id');

        $this->assertTrue($items->has($mathsId));

        return $items->get($mathsId);
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

    public function test_no_one_can_add_homework_after_9_pm_but_send_stays_open_today(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        Carbon::setTestNow(Carbon::parse('2026-09-29 20:30:00', 'Asia/Kolkata'));

        $maths = $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => '2026-09-29',
            'title' => 'Algebra',
            'description' => 'Ex 5.2',
        ]);
        $service->approve($data['admin'], $maths->id);

        Carbon::setTestNow(Carbon::parse('2026-09-29 21:00:00', 'Asia/Kolkata'));

        $this->assertFalse($service->canEnterHomework('2026-09-29'));
        $this->assertTrue($service->canSendHomework('2026-09-29'));

        try {
            $service->submit($data['physicsTeacher'], [
                'batch_id' => $data['batch']->id,
                'course_subject_id' => $data['physics']->id,
                'homework_date' => '2026-09-29',
                'title' => 'Too late',
                'description' => 'Waves',
            ]);
            $this->fail('Homework was saved after 9:00 PM.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Homework entry closes at 9:00 PM. You cannot add homework now.',
                $exception->errors()['homework_date'][0],
            );
        }

        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->assertSee('It is after 9:00 PM')
            ->call('toggleDeskSection', $data['batch']->id)
            ->assertDontSee('Add homework')
            ->assertDontSee('Edit')
            ->assertSee('Send to parents');

        $this->actingAs($data['mathTeacher']);

        Livewire::test(SubmitHomeworkPage::class)
            ->assertSee('It is after 9:00 PM')
            ->assertDontSee('Add homework')
            ->assertDontSee('Update')
            ->call('startAdd', $data['batch']->id, $data['physics']->id)
            ->assertNotified('Homework is closed');
    }

    public function test_past_date_hides_add_and_send_for_everyone(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $maths = $this->submitWhileThatMorningWasOpen($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => '2026-09-28',
            'title' => 'Yesterday algebra',
            'description' => 'Revision',
        ]);
        $service->approve($data['admin'], $maths->id);

        Carbon::setTestNow(Carbon::parse('2026-09-29 10:00:00', 'Asia/Kolkata'));

        $this->assertFalse($service->canEnterHomework('2026-09-28'));
        $this->assertFalse($service->canSendHomework('2026-09-28'));

        $result = $service->combinedSend($data['admin'], $data['batch']->id, '2026-09-28');

        $this->assertSame(0, $result['sent']);
        $this->assertSame('This date has passed. Homework cannot be sent.', $result['error']);

        try {
            $service->submit($data['admin'], [
                'batch_id' => $data['batch']->id,
                'course_subject_id' => $data['physics']->id,
                'homework_date' => '2026-09-28',
                'title' => 'Late add',
                'description' => 'Should not save',
            ], asAdmin: true);
            $this->fail('Homework was saved for a past date.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'This date has passed. Homework cannot be added.',
                $exception->errors()['homework_date'][0],
            );
        }

        $this->actingAs($data['admin']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(HomeworkReviewPage::class)
            ->set('data.homework_date', '2026-09-28')
            ->assertSee('No one can add or send homework now.')
            ->call('toggleDeskSection', $data['batch']->id)
            ->assertSee('Yesterday algebra')
            ->assertDontSee('Add homework')
            ->assertDontSee('Edit')
            ->assertDontSee('Send to parents')
            ->assertDontSee('Resend')
            ->call('sendCombinedForBatch', $data['batch']->id)
            ->assertNotified('Nothing sent');
    }

    public function test_a_second_homework_send_waits_instead_of_sending_again(): void
    {
        $data = $this->seedClass();
        $guard = app(BulkSendGuard::class);
        $key = 'homework-combined:'.$data['batch']->id.':'.now()->toDateString();

        $this->assertTrue($guard->acquire($key));

        $result = app(HomeworkSubmissionService::class)->combinedSend(
            $data['admin'],
            $data['batch']->id,
            now()->toDateString(),
        );

        $guard->release($key);

        $this->assertSame(0, $result['sent']);
        $this->assertSame('This class is already being sent. Please wait.', $result['error']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function submitWhileThatMorningWasOpen(User $user, array $data): HomeworkAssignment
    {
        $returnTo = now();
        Carbon::setTestNow(Carbon::parse($data['homework_date'].' 10:00:00', 'Asia/Kolkata'));

        try {
            return app(HomeworkSubmissionService::class)->submit($user, $data);
        } finally {
            Carbon::setTestNow($returnTo);
        }
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
