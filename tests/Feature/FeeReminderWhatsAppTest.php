<?php

namespace Tests\Feature;

use App\Enums\LeadSource;
use App\Enums\AdmissionStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\FeeReminderStage;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Support\FeeReminderWhatsAppTemplate;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\FeeInstallment;
use App\Models\FeeReminderLog;
use App\Models\FeeStructure;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use App\Services\FeeReminderWhatsAppService;
use App\Services\WhatsAppTemplateParamResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeeReminderWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-test-token'), 'meta_whatsapp');
        Setting::flushValueCache();
    }

    public function test_queues_fee_reminders_for_overdue_students(): void
    {
        $this->travelTo('2026-07-10 10:00:00');

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.FEE123']],
            ], 200),
        ]);

        $staff = $this->createStaffUser();
        [$student, $installment] = $this->createOverdueStudent('9876543210');

        $template = WhatsAppTemplate::query()->create([
            'name' => 'fee_reminder',
            'param_count' => 4,
            'param_mappings' => [
                'student.name',
                'fee.pending_amount',
                'fee.due_date',
                'institute.name',
            ],
            'body' => 'Dear {{1}}, pending {{2}} due {{3}} — {{4}}',
            'is_active' => true,
        ]);

        Setting::setValue('whatsapp.fee_reminder_autosend_enabled', '1', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_send_time', '10:00', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_upcoming_enabled', '0', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_due_enabled', '0', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_overdue_enabled', '1', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_template_id', (string) $template->id, 'whatsapp');

        $service = app(FeeReminderWhatsAppService::class);
        $this->assertCount(1, $service->eligibleStudents());

        $result = $service->maybeQueueDailyReminders($staff);

        $this->assertSame(1, $result['queued'], $result['reason'] ?? 'unknown');

        $campaign = WhatsAppCampaign::query()->first();
        $this->assertNotNull($campaign);
        $this->assertSame('fee_reminder', $campaign->campaignVariable('audience_source'));
        $this->assertCount(1, $campaign->recipients);
        $this->assertDatabaseHas('fee_reminder_logs', [
            'student_id' => $student->id,
            'fee_installment_id' => $installment->id,
            'stage' => 'overdue',
        ]);

        $params = app(WhatsAppTemplateParamResolver::class)->resolveAll(
            $template->paramSources(),
            $student->fresh(),
            $staff,
            null,
            $campaign,
        );

        $this->assertSame($student->name, $params[0]);
        $this->assertSame('5,000.00', $params[1]);
        $this->assertSame('01 Jul 2026', $params[2]);
    }

    public function test_skips_students_within_cooldown(): void
    {
        $this->travelTo('2026-07-10 10:00:00');

        $staff = $this->createStaffUser();
        [$student, $installment] = $this->createOverdueStudent('9876543211');

        FeeReminderLog::query()->create([
            'student_id' => $student->id,
            'fee_installment_id' => $installment->id,
            'stage' => 'overdue',
            'sent_at' => now()->subDays(2),
        ]);

        $template = WhatsAppTemplate::query()->create([
            'name' => 'fee_reminder',
            'param_count' => 1,
            'param_mappings' => ['student.name'],
            'is_active' => true,
        ]);

        Setting::setValue('whatsapp.fee_reminder_autosend_enabled', '1', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_send_time', '10:00', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_upcoming_enabled', '0', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_due_enabled', '0', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_overdue_enabled', '1', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_template_id', (string) $template->id, 'whatsapp');

        $result = app(FeeReminderWhatsAppService::class)->maybeQueueDailyReminders($staff);

        $this->assertSame(0, $result['queued']);
        $this->assertDatabaseCount('whatsapp_campaigns', 0);
    }

    public function test_queues_upcoming_reminder_two_days_before_due(): void
    {
        $this->travelTo('2026-07-25 10:00:00');

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.FEEUP']],
            ], 200),
        ]);

        $staff = $this->createStaffUser();
        [$student, $installment] = $this->createOverdueStudent('9876543299');
        $installment->update(['due_date' => '2026-07-27']);

        $template = WhatsAppTemplate::query()->create([
            'name' => 'fee_reminder_upcoming',
            'param_count' => 4,
            'param_mappings' => [
                'institute.name',
                'student.name',
                'fee.pending_amount',
                'fee.due_date',
            ],
            'body' => FeeReminderWhatsAppTemplate::BODY_UPCOMING,
            'is_active' => true,
        ]);

        Setting::setValue('whatsapp.fee_reminder_autosend_enabled', '1', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_send_time', '10:00', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_days_before', '2', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_upcoming_enabled', '1', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_due_enabled', '0', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_overdue_enabled', '0', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_template_id', (string) $template->id, 'whatsapp');

        $service = app(FeeReminderWhatsAppService::class);
        $this->assertCount(1, $service->eligibleStudentsForStage(FeeReminderStage::Upcoming));

        $result = $service->maybeQueueDailyReminders($staff);

        $this->assertSame(1, $result['queued'], $result['reason'] ?? 'unknown');
        $this->assertDatabaseHas('fee_reminder_logs', [
            'student_id' => $student->id,
            'stage' => 'upcoming',
        ]);
    }

    public function test_queues_due_today_reminder(): void
    {
        $this->travelTo('2026-07-27 10:00:00');

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.FEEDUE']],
            ], 200),
        ]);

        $staff = $this->createStaffUser();
        [$student, $installment] = $this->createOverdueStudent('9876543277');
        $installment->update(['due_date' => '2026-07-27']);

        $template = WhatsAppTemplate::query()->create([
            'name' => 'fee_reminder_due',
            'param_count' => 4,
            'param_mappings' => [
                'institute.name',
                'student.name',
                'fee.pending_amount',
                'fee.due_date',
            ],
            'body' => FeeReminderWhatsAppTemplate::BODY_DUE,
            'is_active' => true,
        ]);

        Setting::setValue('whatsapp.fee_reminder_autosend_enabled', '1', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_send_time', '10:00', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_upcoming_enabled', '0', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_due_enabled', '1', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_overdue_enabled', '0', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_template_id', (string) $template->id, 'whatsapp');

        $result = app(FeeReminderWhatsAppService::class)->maybeQueueDailyReminders($staff);

        $this->assertSame(1, $result['queued'], $result['reason'] ?? 'unknown');
        $this->assertDatabaseHas('fee_reminder_logs', [
            'student_id' => $student->id,
            'stage' => 'due',
        ]);
    }

    public function test_manual_send_queues_for_one_student(): void
    {
        $this->travelTo('2026-07-10 11:00:00');

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.FEEMAN']],
            ], 200),
        ]);

        $staff = $this->createStaffUser();
        [$student] = $this->createOverdueStudent('9876543288');

        $template = WhatsAppTemplate::query()->create([
            'name' => 'fee_reminder_overdue',
            'param_count' => 4,
            'param_mappings' => [
                'institute.name',
                'student.name',
                'fee.pending_amount',
                'fee.due_date',
            ],
            'is_active' => true,
        ]);

        Setting::setValue('whatsapp.fee_reminder_template_id', (string) $template->id, 'whatsapp');

        $result = app(FeeReminderWhatsAppService::class)->sendManual($student, $staff);

        $this->assertSame(1, $result['queued']);
        $this->assertDatabaseHas('fee_reminder_logs', [
            'student_id' => $student->id,
            'stage' => 'manual',
        ]);
    }

    /**
     * @return array{0: Student, 1: FeeInstallment}
     */
    protected function createOverdueStudent(string $mobile): array
    {
        $student = Student::query()->create([
            'name' => 'Riya Sharma',
            'mobile' => $mobile,
            'status' => StudentStatus::Enrolled,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'FEE-WA',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 10000,
            'status' => \App\Enums\CourseStatus::Active,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'ENQ-'.$student->id,
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-'.$student->id,
            'course_fee' => 5000,
            'discount_amount' => 0,
            'net_fee' => 5000,
            'use_installment_plan' => true,
            'status' => AdmissionStatus::Approved,
            'approved_at' => now(),
            'submitted_at' => now(),
        ]);

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
            'enrollment_number' => 'CRM-FEE-'.$student->id,
            'enrolled_at' => now(),
        ]);

        $feeStructure = FeeStructure::query()->create([
            'enrollment_id' => $enrollment->id,
            'course_fee' => 5000,
            'discount_amount' => 0,
            'net_fee' => 5000,
            'paid_amount' => 0,
            'pending_amount' => 5000,
        ]);

        $installment = FeeInstallment::query()->create([
            'fee_structure_id' => $feeStructure->id,
            'label' => 'Installment 1',
            'amount' => 5000,
            'due_date' => '2026-07-01',
            'paid_amount' => 0,
            'pending_amount' => 5000,
            'sort_order' => 1,
        ]);

        return [$student, $installment];
    }

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }
}
