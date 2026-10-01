<?php

namespace Tests\Feature;

use App\Enums\BatchStaffRole;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\HomeworkCheckNotifyStatus;
use App\Enums\HomeworkCheckStatus;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\StudentStatus;
use App\Enums\WhatsAppLiveCampaignStatus;
use App\Filament\Pages\HomeworkCheckPage;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\CourseSubject;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCheck;
use App\Models\HomeworkStudentLink;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppLiveCampaign;
use App\Services\HomeworkSubmissionService;
use App\Models\WhatsAppTemplate;
use App\Services\HomeworkCheckService;
use App\Support\HomeworkNotDoneWhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HomeworkCheckServiceTest extends TestCase
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

    public function test_done_saves_without_whatsapp(): void
    {
        Http::fake();

        [$teacher, $batch, $student, $subject] = $this->seedClass();
        Setting::setValue('whatsapp.homework_not_done_autosend_enabled', '1', 'whatsapp');

        $result = app(HomeworkCheckService::class)->mark(
            $teacher,
            $batch->id,
            $student->id,
            $subject->id,
            'Chapter 5 – Q1 to Q10',
            HomeworkCheckStatus::Done,
        );

        $this->assertSame(HomeworkCheckStatus::Done, $result['check']->status);
        $this->assertSame(HomeworkCheckNotifyStatus::NotRequired, $result['check']->notify_status);
        $this->assertFalse($result['whatsapp']['queued']);
        Http::assertNothingSent();
    }

    public function test_switching_done_and_not_done_updates_the_same_mark_and_profile(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.HW999']],
            ], 200),
        ]);

        [$teacher, $batch, $student, $subject] = $this->seedClass();
        $this->enableHomeworkNotDoneAutomation();
        $service = app(HomeworkCheckService::class);

        $done = $service->mark(
            $teacher,
            $batch->id,
            $student->id,
            $subject->id,
            'Chapter 5',
            HomeworkCheckStatus::Done,
        );
        $notDone = $service->mark(
            $teacher,
            $batch->id,
            $student->id,
            $subject->id,
            'Chapter 5',
            HomeworkCheckStatus::NotDone,
        );

        $this->assertSame($done['check']->id, $notDone['check']->id);
        $this->assertSame(HomeworkCheckStatus::NotDone, $notDone['check']->fresh()->status);
        $this->assertSame(HomeworkCheckNotifyStatus::Sent, $notDone['check']->fresh()->notify_status);
        $this->assertSame(1, HomeworkCheck::query()->count());

        $roster = $service->rosterForBatch($batch->id, $subject->id, null, now()->toDateString())->keyBy('id');
        $this->assertSame('not_done', $roster[$student->id]['status_key']);
        $this->assertSame('Message shared with parents', $roster[$student->id]['parent_line']);

        $days = app(HomeworkSubmissionService::class)->profileDaysForStudent($student);
        $row = $days[0]['subjects'][0] ?? null;
        $this->assertSame('Not Done', $row['check_status'] ?? null);
        $this->assertSame('Message shared with parents', $row['check_note'] ?? null);

        $backToDone = $service->mark(
            $teacher,
            $batch->id,
            $student->id,
            $subject->id,
            'Chapter 5',
            HomeworkCheckStatus::Done,
        );

        $this->assertSame($done['check']->id, $backToDone['check']->id);
        $this->assertSame(HomeworkCheckStatus::Done, $backToDone['check']->fresh()->status);
        $this->assertSame(1, HomeworkCheck::query()->count());
    }

    public function test_not_done_queues_whatsapp_when_configured(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.HW123']],
            ], 200),
        ]);

        [$teacher, $batch, $student, $subject] = $this->seedClass();
        $this->enableHomeworkNotDoneAutomation();

        $result = app(HomeworkCheckService::class)->mark(
            $teacher,
            $batch->id,
            $student->id,
            $subject->id,
            'Chapter 5 – Q1 to Q10',
            HomeworkCheckStatus::NotDone,
        );

        $this->assertSame(HomeworkCheckStatus::NotDone, $result['check']->status);
        $this->assertTrue($result['whatsapp']['queued'], $result['whatsapp']['message']);
        $this->assertSame(HomeworkCheckNotifyStatus::Sent, $result['check']->fresh()->notify_status);
        $this->assertDatabaseHas('homework_checks', [
            'student_id' => $student->id,
            'status' => 'not_done',
            'topic' => 'Chapter 5 – Q1 to Q10',
        ]);
    }

    public function test_not_done_message_reuses_the_student_homework_link(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.HWLINK']],
            ], 200),
        ]);

        [$teacher, $batch, $student, $subject] = $this->seedClass();
        $this->enableHomeworkNotDoneAutomation();

        $assignment = HomeworkAssignment::query()
            ->where('batch_id', $batch->id)
            ->where('course_subject_id', $subject->id)
            ->firstOrFail();

        $link = HomeworkStudentLink::query()->create([
            'homework_assignment_id' => $assignment->id,
            'student_id' => $student->id,
            'token' => 'sameTok1',
            'click_count' => 2,
        ]);

        WhatsAppTemplate::query()->where('name', HomeworkNotDoneWhatsAppTemplate::NAME)->update([
            'param_count' => 7,
            'param_mappings' => HomeworkNotDoneWhatsAppTemplate::mappingSources(),
            'body' => HomeworkNotDoneWhatsAppTemplate::BODY,
        ]);

        $result = app(HomeworkCheckService::class)->mark(
            $teacher,
            $batch->id,
            $student->id,
            $subject->id,
            "Today's homework",
            HomeworkCheckStatus::NotDone,
        );

        $this->assertTrue($result['whatsapp']['queued'], $result['whatsapp']['message']);
        $this->assertSame('Approved homework', $result['check']->fresh()->topic);
        $this->assertSame($assignment->id, $result['check']->fresh()->homework_assignment_id);
        $this->assertSame(1, HomeworkStudentLink::query()->count());
        $this->assertSame('sameTok1', HomeworkStudentLink::query()->first()->token);

        $campaign = WhatsAppCampaign::query()->latest('id')->first();
        $this->assertNotNull($campaign);
        $this->assertSame('10 · Section A', $campaign->campaignVariable('class_section'));
        $this->assertSame('Mathematics', $campaign->campaignVariable('subject'));
        $this->assertSame('Approved homework', $campaign->campaignVariable('topic'));
        $this->assertSame($assignment->homeworkDateLabel(), $campaign->campaignVariable('date_label'));
        $this->assertSame($link->publicUrl(), $campaign->campaignVariable('homework_link'));
        $this->assertStringNotContainsString($link->publicUrl(), (string) $campaign->campaignVariable('topic'));
    }

    public function test_not_done_without_mobile_marks_failed(): void
    {
        Http::fake();

        [$teacher, $batch, $student, $subject] = $this->seedClass(mobile: null);
        $this->enableHomeworkNotDoneAutomation();

        $result = app(HomeworkCheckService::class)->mark(
            $teacher,
            $batch->id,
            $student->id,
            $subject->id,
            'Essay writing',
            HomeworkCheckStatus::NotDone,
        );

        $this->assertFalse($result['whatsapp']['queued']);
        $this->assertSame(HomeworkCheckNotifyStatus::Failed, $result['check']->fresh()->notify_status);
        Http::assertNothingSent();
    }

    public function test_teacher_cannot_mark_unassigned_batch(): void
    {
        [, $batch, $student, $subject] = $this->seedClass();
        $otherTeacher = User::factory()->create(['is_active' => true]);
        $otherTeacher->assignRole(StaffJobRole::Teacher->value);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(HomeworkCheckService::class)->mark(
            $otherTeacher,
            $batch->id,
            $student->id,
            $subject->id,
            'Topic',
            HomeworkCheckStatus::Done,
        );
    }

    public function test_mark_many_not_done_for_selected_students(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::sequence()
                ->push(['messages' => [['id' => 'wamid.HWBULK1']]], 200)
                ->push(['messages' => [['id' => 'wamid.HWBULK2']]], 200),
        ]);

        [$teacher, $batch, $student, $subject] = $this->seedClass();
        $second = Student::query()->create([
            'name' => 'Aman Verma',
            'mobile' => '9123456780',
            'status' => StudentStatus::Enrolled,
        ]);
        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $second->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $teacher->id,
        ]);
        $this->enableHomeworkNotDoneAutomation();

        $result = app(HomeworkCheckService::class)->markMany(
            $teacher,
            $batch->id,
            [$student->id, $second->id],
            $subject->id,
            '',
            HomeworkCheckStatus::NotDone,
        );

        $this->assertSame(2, $result['marked']);
        $this->assertSame(2, $result['whatsappQueued']);
        $this->assertDatabaseHas('homework_checks', [
            'student_id' => $student->id,
            'topic' => 'Approved homework',
            'status' => 'not_done',
        ]);
        $this->assertDatabaseHas('homework_checks', [
            'student_id' => $second->id,
            'status' => 'not_done',
        ]);
    }

    public function test_ticked_done_students_leave_the_rest_not_done_with_one_message_each(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.HWBULKREST']],
            ], 200),
        ]);

        [$teacher, $batch, $finished, $subject] = $this->seedClass();
        $missing = Student::query()->create([
            'name' => 'Aman Verma',
            'mobile' => '9123456780',
            'status' => StudentStatus::Enrolled,
        ]);
        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $missing->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $teacher->id,
        ]);
        $this->enableHomeworkNotDoneAutomation();

        $result = app(HomeworkCheckService::class)->applySelection(
            $teacher,
            $batch->id,
            $subject->id,
            [$finished->id],
            'done',
            "Today's homework",
            now()->toDateString(),
        );

        $this->assertSame(1, $result['done']);
        $this->assertSame(1, $result['not_done']);
        $this->assertSame(1, $result['whatsappQueued']);
        $this->assertDatabaseHas('homework_checks', [
            'student_id' => $finished->id,
            'status' => 'done',
        ]);
        $this->assertDatabaseHas('homework_checks', [
            'student_id' => $missing->id,
            'status' => 'not_done',
        ]);
    }

    public function test_one_not_done_marks_open_students_done_and_keeps_an_earlier_not_done(): void
    {
        Http::fake();

        [$teacher, $batch, $first, $subject] = $this->seedClass();
        $second = Student::query()->create([
            'name' => 'Aman Verma',
            'mobile' => '9123456780',
            'status' => StudentStatus::Enrolled,
        ]);
        $third = Student::query()->create([
            'name' => 'Neha Gupta',
            'mobile' => '9000000001',
            'status' => StudentStatus::Enrolled,
        ]);

        foreach ([$second, $third] as $student) {
            BatchStudent::query()->create([
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'is_active' => true,
                'assigned_at' => now(),
                'assigned_by_user_id' => $teacher->id,
            ]);
        }

        $service = app(HomeworkCheckService::class);
        $date = now()->toDateString();

        $service->mark(
            $teacher,
            $batch->id,
            $third->id,
            $subject->id,
            "Today's homework",
            HomeworkCheckStatus::Done,
            $date,
        );

        $this->assertDatabaseMissing('homework_checks', [
            'student_id' => $first->id,
        ]);
        $this->assertDatabaseMissing('homework_checks', [
            'student_id' => $second->id,
        ]);

        $service->markNotDoneAndCloseOpen(
            $teacher,
            $batch->id,
            $first->id,
            $subject->id,
            "Today's homework",
            $date,
        );

        $this->assertDatabaseHas('homework_checks', [
            'student_id' => $first->id,
            'status' => 'not_done',
        ]);
        $this->assertDatabaseHas('homework_checks', [
            'student_id' => $second->id,
            'status' => 'done',
        ]);
        $this->assertDatabaseHas('homework_checks', [
            'student_id' => $third->id,
            'status' => 'done',
        ]);

        $service->markNotDoneAndCloseOpen(
            $teacher,
            $batch->id,
            $second->id,
            $subject->id,
            "Today's homework",
            $date,
        );

        $this->assertDatabaseHas('homework_checks', [
            'student_id' => $first->id,
            'status' => 'not_done',
        ]);
        $this->assertDatabaseHas('homework_checks', [
            'student_id' => $second->id,
            'status' => 'not_done',
        ]);
        $this->assertDatabaseHas('homework_checks', [
            'student_id' => $third->id,
            'status' => 'done',
        ]);
    }

    public function test_not_done_button_waits_until_the_teacher_confirms(): void
    {
        Http::fake();

        [$teacher, $batch, $student, $subject] = $this->seedClass();
        $other = Student::query()->create([
            'name' => 'Aman Verma',
            'mobile' => '9123456780',
            'status' => StudentStatus::Enrolled,
        ]);
        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $other->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $teacher->id,
        ]);

        $this->actingAs($teacher);

        $page = Livewire::test(HomeworkCheckPage::class)
            ->fillForm([
                'batch_id' => $batch->id,
                'course_subject_id' => $subject->id,
                'check_date' => now()->toDateString(),
            ])
            ->call('askSingleNotDone', $student->id)
            ->assertSet('singleNotDoneStudentId', $student->id);

        $this->assertDatabaseMissing('homework_checks', [
            'student_id' => $student->id,
        ]);

        $page->call('confirmSingleNotDone')
            ->assertSet('singleNotDoneStudentId', null);

        $this->assertDatabaseHas('homework_checks', [
            'student_id' => $student->id,
            'status' => 'not_done',
        ]);
        $this->assertDatabaseHas('homework_checks', [
            'student_id' => $other->id,
            'status' => 'done',
        ]);
    }

    public function test_teacher_homework_list_hides_student_mobile(): void
    {
        [$admin, $batch, $student, $subject] = $this->seedClass(mobile: '9876543210');

        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->assignRole(StaffJobRole::Teacher->value);

        BatchStaffAssignment::query()->create([
            'batch_id' => $batch->id,
            'user_id' => $teacher->id,
            'role' => BatchStaffRole::SubjectTeacher,
            'course_subject_id' => $subject->id,
        ]);

        \App\Models\HomeworkAssignment::query()->create([
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'created_by_user_id' => $teacher->id,
            'title' => 'Today homework',
            'description' => 'Page 12',
            'content_type' => \App\Enums\HomeworkContentType::Text,
            'status' => \App\Enums\HomeworkAssignmentStatus::Sent,
            'homework_date' => now()->toDateString(),
            'published_at' => now(),
        ]);

        $this->actingAs($teacher);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        Livewire::withQueryParams([
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'check_date' => now()->toDateString(),
        ])->test(HomeworkCheckPage::class)
            ->assertSee('Riya Sharma')
            ->assertDontSee('9876543210')
            ->assertDontSee('Mobile');

        $this->actingAs($admin);

        Livewire::withQueryParams([
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'check_date' => now()->toDateString(),
        ])->test(HomeworkCheckPage::class)
            ->assertSee('Riya Sharma')
            ->assertSee('9876543210')
            ->assertSee('Mobile');
    }

    public function test_roster_lists_batch_students(): void
    {
        [$teacher, $batch, $student, $subject] = $this->seedClass();

        $roster = app(HomeworkCheckService::class)->rosterForBatch($batch->id, $subject->id);

        $this->assertCount(1, $roster);
        $this->assertSame($student->id, $roster->first()['id']);
        unset($teacher);
    }

    public function test_roster_shows_link_opened_next_to_not_done(): void
    {
        Http::fake();

        [$teacher, $batch, $riya, $subject] = $this->seedClass();
        $aman = Student::query()->create([
            'name' => 'Aman Verma',
            'mobile' => '9123456780',
            'status' => StudentStatus::Enrolled,
        ]);
        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $aman->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $teacher->id,
        ]);

        $assignment = \App\Models\HomeworkAssignment::query()->create([
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'created_by_user_id' => $teacher->id,
            'title' => 'Algebra worksheet',
            'description' => 'Complete all questions',
            'content_type' => \App\Enums\HomeworkContentType::Text,
            'status' => \App\Enums\HomeworkAssignmentStatus::Sent,
            'homework_date' => now()->toDateString(),
            'published_at' => now(),
        ]);

        $links = app(\App\Services\HomeworkStudentLinkService::class)->ensureForAssignment(
            $assignment,
            collect([$riya, $aman]),
        );

        app(HomeworkCheckService::class)->mark(
            $teacher,
            $batch->id,
            $riya->id,
            $subject->id,
            'Algebra worksheet',
            HomeworkCheckStatus::NotDone,
            now()->toDateString(),
        );

        $roster = app(HomeworkCheckService::class)
            ->rosterForBatch($batch->id, $subject->id, null, now()->toDateString())
            ->keyBy('id');

        $this->assertTrue($roster[$riya->id]['link_tracked']);
        $this->assertFalse($roster[$riya->id]['link_opened']);
        $this->assertSame('Not Done', $roster[$riya->id]['last_status']);
        $this->assertTrue($roster[$aman->id]['link_tracked']);
        $this->assertFalse($roster[$aman->id]['link_opened']);
        $this->assertNull($roster[$aman->id]['last_status']);

        $links->get($riya->id)->recordOpen();

        $afterOpen = app(HomeworkCheckService::class)
            ->rosterForBatch($batch->id, $subject->id, null, now()->toDateString())
            ->keyBy('id');

        $this->assertTrue($afterOpen[$riya->id]['link_opened']);
        $this->assertSame('Not Done', $afterOpen[$riya->id]['last_status']);
        $this->assertFalse($afterOpen[$aman->id]['link_opened']);
        $this->assertNotNull($afterOpen[$riya->id]['link_opened_at']);

        $this->actingAs($teacher);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        Livewire::withQueryParams([
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'check_date' => now()->toDateString(),
        ])->test(HomeworkCheckPage::class)
            ->assertSee('Link opened 1 / 2')
            ->assertSee('Not Done')
            ->assertSee('Opened')
            ->assertSee('Not opened')
            ->assertDontSee('Submit Not Done')
            ->assertDontSee('Mark remaining Done')
            ->assertSee('Done');
    }

    public function test_mark_remaining_done_only_marks_unmarked_students(): void
    {
        Http::fake();

        [$teacher, $batch, $student, $subject] = $this->seedClass();
        $second = Student::query()->create([
            'name' => 'Aman Verma',
            'mobile' => '9123456780',
            'status' => StudentStatus::Enrolled,
        ]);
        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $second->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $teacher->id,
        ]);

        app(HomeworkCheckService::class)->mark(
            $teacher,
            $batch->id,
            $student->id,
            $subject->id,
            'Chapter 1',
            HomeworkCheckStatus::NotDone,
            now()->toDateString(),
        );

        $result = app(HomeworkCheckService::class)->markRemainingDone(
            $teacher,
            $batch->id,
            $subject->id,
            'Chapter 1',
            now()->toDateString(),
        );

        $this->assertSame(1, $result['marked']);
        $done = \App\Models\HomeworkCheck::query()
            ->where('student_id', $second->id)
            ->where('status', 'done')
            ->latest('id')
            ->first();
        $this->assertNotNull($done);
        $this->assertSame(now()->toDateString(), $done->checked_on?->toDateString());
    }

    public function test_marks_can_be_saved_for_a_past_date(): void
    {
        Http::fake();

        [$teacher, $batch, $student, $subject] = $this->seedClass();
        $past = now()->subDay()->toDateString();
        $this->approveHomework($batch->id, $subject->id, $teacher->id, $past);

        $result = app(HomeworkCheckService::class)->mark(
            $teacher,
            $batch->id,
            $student->id,
            $subject->id,
            'Yesterday worksheet',
            HomeworkCheckStatus::Done,
            $past,
        );

        $this->assertSame($past, $result['check']->checked_on?->toDateString());

        $roster = app(HomeworkCheckService::class)->rosterForBatch(
            $batch->id,
            $subject->id,
            null,
            $past,
        );

        $this->assertSame('Done', $roster->first()['last_status']);
    }

    public function test_resend_whatsapp_for_failed_not_done(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.HWRESEND']],
            ], 200),
        ]);

        [$teacher, $batch, $student, $subject] = $this->seedClass(mobile: null);
        $this->enableHomeworkNotDoneAutomation();

        $failed = app(HomeworkCheckService::class)->mark(
            $teacher,
            $batch->id,
            $student->id,
            $subject->id,
            'Essay',
            HomeworkCheckStatus::NotDone,
        );

        $this->assertSame(HomeworkCheckNotifyStatus::Failed, $failed['check']->notify_status);

        $student->update(['mobile' => '9876543299']);

        $resent = app(HomeworkCheckService::class)->resendWhatsApp($teacher, $failed['check']->id);

        $this->assertTrue($resent['queued'], $resent['message']);
        $this->assertSame(HomeworkCheckNotifyStatus::Sent, $resent['check']->notify_status);
        $this->assertNotNull($resent['campaign_id']);
    }

    public function test_homework_check_page_is_accessible_with_permission(): void
    {
        [$teacher] = $this->seedClass();
        $this->actingAs($teacher);

        Livewire::test(HomeworkCheckPage::class)
            ->assertSuccessful();
    }

    public function test_check_page_hides_marks_until_homework_is_given(): void
    {
        [$teacher, $batch, $student, $subject] = $this->seedClass(approvedHomework: false);
        $this->actingAs($teacher);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        Livewire::withQueryParams([
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'check_date' => now()->toDateString(),
        ])->test(HomeworkCheckPage::class)
            ->assertSee('No homework was given')
            ->assertDontSee($student->name)
            ->assertDontSee('Marks for');

        \App\Models\HomeworkAssignment::query()->create([
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'created_by_user_id' => $teacher->id,
            'title' => 'Algebra worksheet',
            'description' => 'Complete all questions',
            'content_type' => \App\Enums\HomeworkContentType::Text,
            'status' => \App\Enums\HomeworkAssignmentStatus::Sent,
            'homework_date' => now()->toDateString(),
            'published_at' => now(),
        ]);

        Livewire::withQueryParams([
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'check_date' => now()->toDateString(),
        ])->test(HomeworkCheckPage::class)
            ->assertSee($student->name)
            ->assertSee('Done')
            ->assertSee('Not done')
            ->assertDontSee('Marks for')
            ->assertDontSee('No homework was given');

        Livewire::withQueryParams([]);
    }

    public function test_done_and_not_done_wait_until_admin_approves(): void
    {
        [$teacher, $batch, $student, $subject] = $this->seedClass(approvedHomework: false);
        $this->actingAs($teacher);
        \Filament\Facades\Filament::setCurrentPanel(\Filament\Facades\Filament::getPanel('admin'));

        $assignment = \App\Models\HomeworkAssignment::query()->create([
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'created_by_user_id' => $teacher->id,
            'title' => 'Waiting homework',
            'description' => 'Page 1',
            'content_type' => \App\Enums\HomeworkContentType::Text,
            'status' => \App\Enums\HomeworkAssignmentStatus::Submitted,
            'homework_date' => now()->toDateString(),
            'published_at' => now(),
        ]);

        Livewire::withQueryParams([
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'check_date' => now()->toDateString(),
        ])->test(HomeworkCheckPage::class)
            ->assertSee('waiting for admin approval')
            ->assertDontSee($student->name)
            ->assertDontSee('Not done');

        $blocked = false;

        try {
            app(HomeworkCheckService::class)->mark(
                $teacher,
                $batch->id,
                $student->id,
                $subject->id,
                'Waiting homework',
                HomeworkCheckStatus::Done,
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $blocked = true;
            $this->assertSame(
                'Admin has not approved this homework yet.',
                $exception->errors()['status'][0] ?? null,
            );
        }

        $this->assertTrue($blocked);

        $assignment->update([
            'status' => \App\Enums\HomeworkAssignmentStatus::Approved,
            'approved_at' => now(),
        ]);

        Livewire::withQueryParams([
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'check_date' => now()->toDateString(),
        ])->test(HomeworkCheckPage::class)
            ->assertSee($student->name)
            ->assertSee('Not done')
            ->assertDontSee('waiting for admin approval');
    }

    public function test_multi_subject_grid_shows_separate_cells_per_subject(): void
    {
        Http::fake();

        [$teacher, $batch, $student, $maths] = $this->seedClass();
        $physics = CourseSubject::query()->create([
            'course_id' => $batch->course_id,
            'name' => 'Physics',
            'code' => 'PHY',
            'default_max_marks' => 100,
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $chemistry = CourseSubject::query()->create([
            'course_id' => $batch->course_id,
            'name' => 'Chemistry',
            'code' => 'CHEM',
            'default_max_marks' => 100,
            'sort_order' => 3,
            'is_active' => true,
        ]);
        $batch->subjects()->attach([
            $physics->id => ['sort_order' => 2],
            $chemistry->id => ['sort_order' => 3],
        ]);

        $this->approveHomework($batch->id, $physics->id, $teacher->id);

        $service = app(HomeworkCheckService::class);

        $service->mark(
            $teacher,
            $batch->id,
            $student->id,
            $physics->id,
            'Laws of motion',
            HomeworkCheckStatus::NotDone,
        );
        $service->mark(
            $teacher,
            $batch->id,
            $student->id,
            $maths->id,
            'Integrals',
            HomeworkCheckStatus::Done,
        );

        $grid = $service->multiSubjectGridForBatch($teacher, $batch->id, now()->toDateString());

        $subjectIds = collect($grid['subjects'])->pluck('id')->all();
        $this->assertContains($physics->id, $subjectIds);
        $this->assertContains($chemistry->id, $subjectIds);
        $this->assertContains($maths->id, $subjectIds);

        $row = collect($grid['students'])->firstWhere('id', $student->id);
        $this->assertNotNull($row);
        $this->assertSame('Not Done', $row['cells'][$physics->id]['status']);
        $this->assertSame('Done', $row['cells'][$maths->id]['status']);
        $this->assertNull($row['cells'][$chemistry->id]['status']);
        $this->assertSame(1, $grid['summary']['not_done']);
        $this->assertSame(1, $grid['summary']['done']);
        $this->assertSame(1, $grid['summary']['unmarked']);
    }

    public function test_homework_check_page_marks_cell_for_subject(): void
    {
        Http::fake();

        [$teacher, $batch, $student, $subject] = $this->seedClass();
        $this->actingAs($teacher);

        Livewire::test(HomeworkCheckPage::class)
            ->fillForm([
                'batch_id' => $batch->id,
                'course_subject_id' => $subject->id,
                'check_date' => now()->toDateString(),
            ])
            ->set('selectedStudentIds', [$student->id])
            ->call('openBulkAsk')
            ->assertSet('bulkStep', 'ask')
            ->call('chooseBulk', 'not_done')
            ->assertSet('bulkStep', 'confirm')
            ->call('confirmBulk')
            ->assertSet('bulkStep', '')
            ->assertNotified();

        $this->assertDatabaseHas('homework_checks', [
            'student_id' => $student->id,
            'course_subject_id' => $subject->id,
            'status' => 'not_done',
        ]);
    }

    public function test_subject_auto_selects_when_teacher_has_only_one(): void
    {
        [$teacher, $batch, $student, $subject] = $this->seedClass();
        $this->actingAs($teacher);

        \App\Models\BatchStaffAssignment::query()
            ->where('batch_id', $batch->id)
            ->where('user_id', $teacher->id)
            ->delete();

        $teacher->syncRoles([\App\Enums\RoleName::Staff->value]);
        \Spatie\Permission\Models\Permission::findOrCreate(\App\Enums\CrmPermission::HomeworkManage->value, 'web');
        $teacher->givePermissionTo(\App\Enums\CrmPermission::HomeworkManage->value);

        \App\Models\BatchStaffAssignment::query()->create([
            'batch_id' => $batch->id,
            'user_id' => $teacher->id,
            'role' => \App\Enums\BatchStaffRole::SubjectTeacher,
            'course_subject_id' => $subject->id,
        ]);

        Livewire::test(HomeworkCheckPage::class)
            ->set('data.check_date', now()->toDateString())
            ->set('data.batch_id', $batch->id)
            ->assertSet('data.course_subject_id', $subject->id);

        unset($student);
    }

    public function test_not_done_count_this_week(): void
    {
        Http::fake();

        [$teacher, $batch, $student, $subject] = $this->seedClass();

        app(HomeworkCheckService::class)->mark(
            $teacher,
            $batch->id,
            $student->id,
            $subject->id,
            'A',
            HomeworkCheckStatus::NotDone,
            now()->toDateString(),
        );

        $this->assertSame(1, app(HomeworkCheckService::class)->notDoneCountThisWeek($student->id));

        app(HomeworkCheckService::class)->mark(
            $teacher,
            $batch->id,
            $student->id,
            $subject->id,
            'B',
            HomeworkCheckStatus::Done,
            now()->toDateString(),
        );

        $this->assertSame(0, app(HomeworkCheckService::class)->notDoneCountThisWeek($student->id));
    }

    public function test_mark_can_link_portal_homework_assignment(): void
    {
        Http::fake();

        [$teacher, $batch, $student, $subject] = $this->seedClass();
        $assignment = \App\Models\HomeworkAssignment::query()->create([
            'batch_id' => $batch->id,
            'created_by_user_id' => $teacher->id,
            'title' => 'Algebra worksheet',
            'description' => 'Complete all questions',
            'content_type' => \App\Enums\HomeworkContentType::Text,
            'published_at' => now(),
        ]);

        $result = app(HomeworkCheckService::class)->mark(
            $teacher,
            $batch->id,
            $student->id,
            $subject->id,
            '',
            HomeworkCheckStatus::Done,
            now()->toDateString(),
            $assignment->id,
        );

        $this->assertSame($assignment->id, $result['check']->homework_assignment_id);
        $this->assertSame('Algebra worksheet', $result['check']->topic);
    }

    protected function approveHomework(int $batchId, int $subjectId, int $userId, ?string $date = null): void
    {
        \App\Models\HomeworkAssignment::query()->create([
            'batch_id' => $batchId,
            'course_subject_id' => $subjectId,
            'created_by_user_id' => $userId,
            'approved_by_user_id' => $userId,
            'title' => 'Approved homework',
            'description' => 'Class work',
            'content_type' => \App\Enums\HomeworkContentType::Text,
            'status' => \App\Enums\HomeworkAssignmentStatus::Approved,
            'homework_date' => $date ?? now()->toDateString(),
            'published_at' => now(),
            'approved_at' => now(),
        ]);
    }

    /**
     * @return array{0: User, 1: Batch, 2: Student, 3: CourseSubject}
     */
    protected function seedClass(?string $mobile = '9876543210', bool $approvedHomework = true): array
    {
        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->assignRole(RoleName::SuperAdmin->value);

        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'CLS-10',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        $subject = CourseSubject::query()->create([
            'course_id' => $course->id,
            'name' => 'Mathematics',
            'code' => 'MATH',
            'default_max_marks' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $batch = Batch::query()->create([
            'name' => 'Class 10 - A',
            'section' => 'A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        $batch->subjects()->attach($subject->id, ['sort_order' => 1]);

        BatchStaffAssignment::query()->create([
            'batch_id' => $batch->id,
            'user_id' => $teacher->id,
            'role' => BatchStaffRole::LeadTeacher,
            'course_subject_id' => null,
        ]);

        $student = Student::query()->create([
            'name' => 'Riya Sharma',
            'mobile' => $mobile,
            'status' => StudentStatus::Enrolled,
        ]);

        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $teacher->id,
        ]);

        if ($approvedHomework) {
            $this->approveHomework($batch->id, $subject->id, $teacher->id);
        }

        return [$teacher, $batch, $student, $subject];
    }

    protected function enableHomeworkNotDoneAutomation(): void
    {
        $meta = MetaWhatsAppTemplate::query()->create([
            'name' => HomeworkNotDoneWhatsAppTemplate::NAME,
            'language' => 'en',
            'status' => 'APPROVED',
            'param_count' => 5,
            'param_mappings' => [
                'student.name',
                'homework.class_section',
                'homework.subject',
                'homework.topic',
                'institute.name',
            ],
            'body' => HomeworkNotDoneWhatsAppTemplate::BODY,
            'is_active' => true,
            'synced_at' => now(),
        ]);

        WhatsAppTemplate::query()->create([
            'name' => HomeworkNotDoneWhatsAppTemplate::NAME,
            'param_count' => 5,
            'param_mappings' => [
                'student.name',
                'homework.class_section',
                'homework.subject',
                'homework.topic',
                'institute.name',
            ],
            'body' => HomeworkNotDoneWhatsAppTemplate::BODY,
            'is_active' => true,
            'synced_at' => now(),
        ]);

        $live = WhatsAppLiveCampaign::query()->create([
            'name' => 'homework_not_done_live',
            'meta_whatsapp_template_id' => $meta->id,
            'status' => WhatsAppLiveCampaignStatus::Live,
            'went_live_at' => now(),
        ]);

        Setting::setValue('whatsapp.homework_not_done_autosend_enabled', '1', 'whatsapp');
        Setting::setValue('whatsapp.homework_not_done_live_campaign_id', (string) $live->id, 'whatsapp');
        Setting::flushValueCache();
    }
}
