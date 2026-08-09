<?php

namespace Tests\Feature;

use App\Enums\BatchStaffRole;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\HomeworkCheckNotifyStatus;
use App\Enums\HomeworkCheckStatus;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\WhatsAppLiveCampaignStatus;
use App\Filament\Pages\HomeworkCheckPage;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\CourseSubject;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppLiveCampaign;
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
        $otherTeacher->assignRole(RoleName::Staff->value);

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
            'topic' => "Today's homework",
            'status' => 'not_done',
        ]);
        $this->assertDatabaseHas('homework_checks', [
            'student_id' => $second->id,
            'status' => 'not_done',
        ]);
    }

    public function test_roster_lists_batch_students(): void
    {
        [$teacher, $batch, $student, $subject] = $this->seedClass();

        $roster = app(HomeworkCheckService::class)->rosterForBatch($batch->id, $subject->id);

        $this->assertCount(1, $roster);
        $this->assertSame($student->id, $roster->first()['id']);
        unset($teacher);
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
    }

    public function test_homework_check_page_is_accessible_with_permission(): void
    {
        [$teacher] = $this->seedClass();
        $this->actingAs($teacher);

        Livewire::test(HomeworkCheckPage::class)
            ->assertSuccessful();
    }

    /**
     * @return array{0: User, 1: Batch, 2: Student, 3: CourseSubject}
     */
    protected function seedClass(?string $mobile = '9876543210'): array
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
