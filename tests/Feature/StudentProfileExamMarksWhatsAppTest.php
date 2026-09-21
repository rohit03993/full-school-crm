<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Filament\Pages\StudentProfilePage;
use App\Filament\Pages\TestMarksReviewPage;
use App\Jobs\RunWhatsAppCampaignJob;
use App\Models\AcademicSession;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Admission;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppTemplate;
use App\Services\ActivityAttendanceService;
use App\Services\ActivityMarksWhatsAppService;
use App\Support\StudentExamMarksMatrix;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentProfileExamMarksWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-19 10:00:00');

        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-test-token'), 'meta_whatsapp');
        Setting::flushValueCache();
    }

    public function test_create_marks_campaign_can_target_one_student_and_refresh_keeps_that_filter(): void
    {
        Queue::fake();

        $this->seed(\Database\Seeders\ActivityTypeSeeder::class);

        $staff = $this->createSuperAdmin();
        [$studentA, $studentB, $groupKey] = $this->createClassWithTwoMarkedStudents($staff);
        $template = $this->createMarksTemplate();

        $service = app(ActivityMarksWhatsAppService::class);
        $campaign = $service->createMarksCampaign(
            $staff,
            $template,
            $groupKey,
            'Unit Test — Sept 2026',
            '2026-09-12',
            $studentA->id,
        );

        $this->assertSame(1, $campaign->total_recipients);
        $this->assertSame([$studentA->id], $campaign->recipients()->pluck('student_id')->all());
        $this->assertSame((string) $studentA->id, (string) $campaign->campaignVariable('only_student_id'));
        $this->assertStringContainsString($studentA->name, $campaign->name);

        $preview = $service->previewForStudent($studentA, $groupKey);
        $this->assertNotNull($preview);
        $this->assertSame('9876501001', $preview['mobile']);
        $this->assertStringContainsString('Maths: 42/50', $preview['marks_summary']);
        $this->assertNull($preview['prior_send']);
        $this->assertNotNull($service->previewForStudent($studentB, $groupKey));
        $this->assertNull($service->previewForStudent($studentA, $groupKey.'-missing'));

        $queued = $service->queueMarksCampaign(
            $staff,
            $template->id,
            $groupKey,
            'Unit Test — Sept 2026',
            '2026-09-12',
            $studentA->id,
        );

        $this->assertSame(WhatsAppCampaignStatus::Queued, $queued->status);
        $this->assertSame(1, $queued->recipients()->count());
        $this->assertSame([$studentA->id], $queued->recipients()->pluck('student_id')->all());
        $queuedPreview = $service->previewForStudent($studentA->fresh(), $groupKey);
        $this->assertSame('queued', $queuedPreview['prior_send']['status'] ?? null);
        $this->assertSame('profile', $queuedPreview['prior_send']['source'] ?? null);
        Queue::assertPushed(RunWhatsAppCampaignJob::class, fn (RunWhatsAppCampaignJob $job): bool => $job->campaignId === $queued->id);
    }

    public function test_profile_shows_whatsapp_only_when_the_student_appeared_and_queues_that_student(): void
    {
        Queue::fake();

        $this->seed(\Database\Seeders\ActivityTypeSeeder::class);

        $admin = $this->createSuperAdmin();
        [$studentA, $studentB, $groupKey] = $this->createClassWithTwoMarkedStudents($admin, markSecond: false);
        $this->createMarksTemplate();

        $this->actingAs($admin);

        $appeared = Livewire::test(StudentProfilePage::class, ['record' => $studentA])
            ->set('profileTab', 'activities')
            ->assertSee('Unit Test — Sept 2026')
            ->assertSeeHtml('confirmSendExamMarksWhatsApp');

        $toolbarNames = collect($appeared->instance()->studentProfileDeskToolbar()['primary'])
            ->pluck('name')
            ->merge(collect($appeared->instance()->studentProfileDeskToolbar()['more'])->pluck('name'));
        $this->assertFalse($toolbarNames->contains('sendExamMarksWhatsApp'));

        $appeared
            ->call('confirmSendExamMarksWhatsApp', $groupKey)
            ->assertActionMounted('sendExamMarksWhatsApp')
            ->callMountedAction()
            ->assertNotified()
            ->assertRedirect();

        $campaign = WhatsAppCampaign::query()->latest('id')->first();
        $this->assertNotNull($campaign);
        $this->assertSame(1, $campaign->recipients()->count());
        $this->assertSame($studentA->id, $campaign->recipients()->value('student_id'));
        $this->assertSame((string) $studentA->id, (string) $campaign->campaignVariable('only_student_id'));

        Livewire::test(StudentProfilePage::class, ['record' => $studentB])
            ->set('profileTab', 'activities')
            ->assertSee('Unit Test — Sept 2026')
            ->assertDontSeeHtml('confirmSendExamMarksWhatsApp');
    }

    public function test_profile_asks_to_resend_when_class_sheet_already_sent(): void
    {
        $this->seed(\Database\Seeders\ActivityTypeSeeder::class);

        $admin = $this->createSuperAdmin();
        [$studentA, $studentB, $groupKey] = $this->createClassWithTwoMarkedStudents($admin);
        $template = $this->createMarksTemplate();

        $campaign = WhatsAppCampaign::query()->create([
            'whatsapp_template_id' => $template->id,
            'name' => 'Marks · Unit Test — Sept 2026',
            'status' => WhatsAppCampaignStatus::Completed,
            'total_recipients' => 2,
            'sent_count' => 1,
            'failed_count' => 0,
            'shot_at' => now(),
            'finished_at' => now(),
            'campaign_variables' => [
                'audience_source' => 'activity_marks',
                'test_key' => $groupKey,
            ],
        ]);

        WhatsAppCampaignRecipient::query()->create([
            'whatsapp_campaign_id' => $campaign->id,
            'student_id' => $studentA->id,
            'phone' => (string) $studentA->mobile,
            'status' => WhatsAppRecipientStatus::Sent,
        ]);

        $preview = app(ActivityMarksWhatsAppService::class)->previewForStudent($studentA, $groupKey);
        $this->assertSame('sent', $preview['prior_send']['status'] ?? null);
        $this->assertSame('class_sheet', $preview['prior_send']['source'] ?? null);
        $this->assertNull(app(ActivityMarksWhatsAppService::class)->previewForStudent($studentB, $groupKey)['prior_send']);

        $copy = app(ActivityMarksWhatsAppService::class)->confirmCopyForStudent($studentA, $groupKey);
        $this->assertSame('Already sent — send again?', $copy['heading']);
        $this->assertSame('Resend now', $copy['submit']);
        $this->assertStringContainsString('class mark sheet', $copy['description']);
        $this->assertStringContainsString('already received this test', $copy['description']);

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $studentA])
            ->set('profileTab', 'activities')
            ->assertSee('Resend')
            ->call('confirmSendExamMarksWhatsApp', $groupKey)
            ->assertActionMounted('sendExamMarksWhatsApp');
    }

    public function test_class_sheet_send_history_counts_staff_and_switches_to_resend(): void
    {
        $this->seed(\Database\Seeders\ActivityTypeSeeder::class);

        $admin = $this->createSuperAdmin();
        $admin->update(['name' => 'Khushi Mam']);
        [$studentA, $studentB, $groupKey] = $this->createClassWithTwoMarkedStudents($admin);
        $this->createMarksTemplate();

        $service = app(ActivityMarksWhatsAppService::class);
        $empty = $service->classSheetSendHistory($groupKey);

        $this->assertSame(2, $empty['eligible_now']);
        $this->assertFalse($empty['has_prior_class_send']);
        $this->assertSame('Queue WhatsApp to all students with marks', $empty['button_label']);
        $this->assertSame([], $empty['sends']);

        $profileOnly = $service->createMarksCampaign(
            $admin,
            WhatsAppTemplate::query()->where('name', 'test_marks')->firstOrFail(),
            $groupKey,
            'Unit Test — Sept 2026',
            '2026-09-12',
            $studentA->id,
        );
        $profileOnly->update([
            'status' => WhatsAppCampaignStatus::Completed,
            'sent_count' => 1,
            'shot_by' => $admin->id,
            'shot_at' => now(),
        ]);

        $stillEmpty = $service->classSheetSendHistory($groupKey);
        $this->assertFalse($stillEmpty['has_prior_class_send']);

        $classCampaign = $service->createMarksCampaign(
            $admin,
            WhatsAppTemplate::query()->where('name', 'test_marks')->firstOrFail(),
            $groupKey,
            'Unit Test — Sept 2026',
            '2026-09-12',
        );
        $classCampaign->update([
            'status' => WhatsAppCampaignStatus::Completed,
            'sent_count' => 2,
            'failed_count' => 0,
            'shot_by' => $admin->id,
            'shot_at' => now(),
        ]);

        $history = $service->classSheetSendHistory($groupKey);
        $this->assertTrue($history['has_prior_class_send']);
        $this->assertSame('Resend WhatsApp to all students with marks', $history['button_label']);
        $this->assertCount(1, $history['sends']);
        $this->assertSame('Khushi Mam', $history['sends'][0]['staff_name']);
        $this->assertSame(2, $history['sends'][0]['sent']);
        $this->assertSame(2, $history['sends'][0]['total']);
        $this->assertSame('2 delivered · 0 failed', $history['sends'][0]['result_line']);
        $this->assertStringNotContainsString('queue', $history['sends'][0]['result_line']);

        $trail = \App\Support\ResultAuditTrail::entriesForGroupKey($groupKey);
        $whatsapp = $trail->first(fn ($entry): bool => $entry->action === 'marks_whatsapp_sent');
        $this->assertNotNull($whatsapp);
        $this->assertSame('Khushi Mam', $whatsapp->user_name);
        $this->assertSame('2 delivered · 0 failed', $whatsapp->detail);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::withQueryParams(['group' => $groupKey])
            ->test(TestMarksReviewPage::class)
            ->assertSuccessful()
            ->assertSee('Resend WhatsApp to all students with marks')
            ->assertSee('Khushi Mam')
            ->assertSee('WhatsApp marks sent')
            ->assertSee('Messages sent for this exam')
            ->assertSee('Eligible students with marks and mobile numbers')
            ->assertSee('2 delivered')
            ->assertSee('0 failed')
            ->assertDontSee('in queue');
    }

    /**
     * @return array{0: Student, 1: Student, 2: string}
     */
    protected function createClassWithTwoMarkedStudents(User $staff, bool $markSecond = true): array
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
            'name' => 'Class 12 Science',
            'code' => 'WA-EX-12',
            'programme_category' => 'coaching',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $batch = Batch::query()->create([
            'name' => '12-A Marks WA',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);

        $studentA = $this->createEnrolledStudent($staff, $course, $batch, $session, 'Aarav Marks', '9876501001', 'WA-1001');
        $studentB = $this->createEnrolledStudent($staff, $course, $batch, $session, 'Bhavya Marks', '9876501002', 'WA-1002');

        $examType = ActivityType::query()->where('slug', 'exam')->firstOrFail();
        $testName = 'Unit Test — Sept 2026';
        $testDate = '2026-09-12';
        $groupKey = Str::slug($testName).'-'.$testDate;
        $attendance = app(ActivityAttendanceService::class);

        $mathSession = ActivitySession::query()->create([
            'activity_type_id' => $examType->id,
            'title' => "{$testName} — Mathematics",
            'batch_id' => $batch->id,
            'session_date' => $testDate,
            'metadata' => [
                'test_key' => $groupKey,
                'test_name' => $testName,
                'subject' => 'Mathematics',
                'max_marks' => 50,
            ],
            'created_by_user_id' => $staff->id,
        ]);

        $scores = [$studentA->id => 42];

        if ($markSecond) {
            $scores[$studentB->id] = 38;
        }

        $attendance->importStudentScores($mathSession, $scores, $staff);

        $this->assertTrue(StudentExamMarksMatrix::forStudent($studentA->fresh(), $examType->id)['rows'][0]['appeared']);

        return [$studentA->fresh(), $studentB->fresh(), $groupKey];
    }

    protected function createEnrolledStudent(
        User $staff,
        Course $course,
        Batch $batch,
        AcademicSession $session,
        string $name,
        string $mobile,
        string $roll,
    ): Student {
        $student = Student::query()->create([
            'name' => $name,
            'father_name' => 'Parent',
            'date_of_birth' => '2008-05-15',
            'gender' => Gender::Male,
            'mobile' => $mobile,
            'status' => StudentStatus::Enrolled,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-'.$roll,
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'CRM-ADM-'.$roll,
            'status' => AdmissionStatus::Approved,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'enrollment_number' => $roll,
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $staff->id,
        ]);

        return $student->fresh(['activeEnrollment', 'activeBatchStudent']);
    }

    protected function createMarksTemplate(): WhatsAppTemplate
    {
        return WhatsAppTemplate::query()->create([
            'name' => 'test_marks',
            'param_count' => 4,
            'param_mappings' => [
                'student.name',
                'student.enrollment_number',
                'activity.test_name',
                'activity.marks_summary',
            ],
            'body' => 'Hi {{1}}, Roll {{2}} — {{3}}: {{4}}',
            'is_active' => true,
        ]);
    }

    protected function createSuperAdmin(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }
}
