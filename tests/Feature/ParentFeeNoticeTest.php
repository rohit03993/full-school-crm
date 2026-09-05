<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\LicenseFeature;
use App\Enums\ParentFeeNoticeStatus;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\ParentFeeNotice;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use App\Services\ParentFeeNoticeService;
use App\Services\WhatsAppTemplateParamResolver;
use App\Support\FeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ParentFeeNoticeTest extends TestCase
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

    public function test_send_creates_campaign_notices_and_resolves_per_student_params(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.NOTICE1']],
            ], 200),
        ]);

        [$staff, $batch, $studentA, $studentB] = $this->seedBatchWithTwoStudents();

        $template = WhatsAppTemplate::query()->create([
            'name' => 'fee_reminder',
            'param_count' => 4,
            'param_mappings' => [
                'institute.name',
                'student.name',
                'fee.pending_amount',
                'fee.due_date',
            ],
            'body' => 'From {{1}}: {{2}} pending {{3}} due {{4}}',
            'is_active' => true,
        ]);

        $result = app(ParentFeeNoticeService::class)->send(
            $batch,
            $template,
            [
                [
                    'student_id' => $studentA->id,
                    'include' => true,
                    'amount' => '2500.50',
                    'due_date' => '2026-09-15',
                ],
                [
                    'student_id' => $studentB->id,
                    'include' => true,
                    'amount' => '1000',
                    'due_date' => '2026-09-20',
                ],
            ],
            $staff,
        );

        $this->assertSame(2, $result['queued']);
        $this->assertSame(1, WhatsAppCampaign::query()->count());
        $this->assertSame(2, ParentFeeNotice::query()->count());

        $campaign = WhatsAppCampaign::query()->first();
        $this->assertSame('parent_fee_notice', $campaign->campaignVariable('audience_source'));

        $paramsA = app(WhatsAppTemplateParamResolver::class)->resolveAll(
            $template->paramSources(),
            $studentA->fresh(),
            $staff,
            null,
            $campaign,
        );

        $this->assertSame($studentA->name, $paramsA[1]);
        $this->assertSame('2,500.50', $paramsA[2]);
        $this->assertSame('15 Sep 2026', $paramsA[3]);

        $this->assertDatabaseHas('parent_fee_notices', [
            'student_id' => $studentA->id,
            'status' => ParentFeeNoticeStatus::Queued->value,
            'sent_by_user_id' => $staff->id,
        ]);
    }

    public function test_send_requires_amount_and_due_date_for_included_students(): void
    {
        [$staff, $batch, $studentA] = $this->seedBatchWithOneStudent();

        $template = WhatsAppTemplate::query()->create([
            'name' => 'fee_reminder',
            'param_count' => 4,
            'param_mappings' => ['institute.name', 'student.name', 'fee.pending_amount', 'fee.due_date'],
            'body' => 'x',
            'is_active' => true,
        ]);

        $this->expectException(ValidationException::class);

        app(ParentFeeNoticeService::class)->send(
            $batch,
            $template,
            [[
                'student_id' => $studentA->id,
                'include' => true,
                'amount' => '',
                'due_date' => '2026-09-15',
            ]],
            $staff,
        );
    }

    public function test_roster_and_notices_work_when_fees_module_conceptually_unused(): void
    {
        [$staff, $batch, $studentA] = $this->seedBatchWithOneStudent();

        $roster = app(ParentFeeNoticeService::class)->rosterForBatch($batch);

        $this->assertCount(1, $roster);
        $this->assertTrue($roster[0]['has_mobile']);
        $this->assertTrue(FeatureGate::enabled(LicenseFeature::WhatsApp));

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.NOTICE2']],
            ], 200),
        ]);

        $template = WhatsAppTemplate::query()->create([
            'name' => 'fee_reminder_overdue',
            'param_count' => 4,
            'param_mappings' => ['institute.name', 'student.name', 'fee.pending_amount', 'fee.due_date'],
            'body' => 'x',
            'is_active' => true,
        ]);

        app(ParentFeeNoticeService::class)->send(
            $batch,
            $template,
            [[
                'student_id' => $studentA->id,
                'include' => true,
                'amount' => '750',
                'due_date' => '2026-10-01',
            ]],
            $staff,
        );

        $notices = app(ParentFeeNoticeService::class)->noticesForStudent($studentA->fresh());

        $this->assertCount(1, $notices);
        $this->assertSame('750.00', (string) $notices->first()->amount);
    }

    /**
     * @return array{0: User, 1: Batch, 2: Student, 3: Student}
     */
    protected function seedBatchWithTwoStudents(): array
    {
        [$staff, $batch, $studentA] = $this->seedBatchWithOneStudent('9000001001', 'ROLL-A');
        $studentB = $this->makeEnrolledInBatch($batch, $staff, 'Student B', '9000001002', 'ROLL-B');

        return [$staff, $batch, $studentA, $studentB];
    }

    public function test_preview_fills_body_for_first_selected_student(): void
    {
        Setting::setValue('site.name', 'Motion Academy', 'site');
        Setting::flushValueCache();

        [, , $student] = $this->seedBatchWithOneStudent();

        $template = WhatsAppTemplate::query()->create([
            'name' => 'fee_reminder_overdue',
            'param_count' => 4,
            'param_mappings' => [
                'institute.name',
                'student.name',
                'fee.pending_amount',
                'fee.due_date',
            ],
            'body' => "From {{1}}\nStudent: {{2}}\nAmount: {{3}}\nDue: {{4}}",
            'is_active' => true,
        ]);

        $preview = app(ParentFeeNoticeService::class)->preview([
            [
                'student_id' => $student->id,
                'include' => true,
                'has_mobile' => true,
                'amount' => '20000',
                'due_date' => '2026-09-24',
            ],
        ], $template->id);

        $this->assertTrue($preview['ready']);
        $this->assertSame($student->name, $preview['student_name']);
        $this->assertStringContainsString('Motion Academy', (string) $preview['body']);
        $this->assertStringContainsString('20,000.00', (string) $preview['body']);
        $this->assertStringContainsString('24 Sep 2026', (string) $preview['body']);
    }

    public function test_preview_warns_when_template_has_zero_params(): void
    {
        [, , $student] = $this->seedBatchWithOneStudent();

        $template = WhatsAppTemplate::query()->create([
            'name' => 'test1',
            'param_count' => 0,
            'param_mappings' => [],
            'body' => 'Hello parent',
            'is_active' => true,
        ]);

        $preview = app(ParentFeeNoticeService::class)->preview([
            [
                'student_id' => $student->id,
                'include' => true,
                'has_mobile' => true,
                'amount' => '20000',
                'due_date' => '2026-09-24',
            ],
        ], $template->id);

        $this->assertFalse($preview['ready']);
        $this->assertStringContainsString('0 variables', (string) $preview['warning']);
        $this->assertSame('Hello parent', $preview['body']);
    }

    /**
     * @return array{0: User, 1: Batch, 2: Student}
     */
    protected function seedBatchWithOneStudent(string $mobile = '9000001001', string $roll = 'ROLL-A'): array
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole(RoleName::SuperAdmin->value);

        $session = AcademicSession::query()->create([
            'name' => '2026-27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 11',
            'code' => 'CLS-11-PFN',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        $batch = Batch::query()->create([
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'name' => '11-A Notices',
            'status' => BatchStatus::Active,
        ]);

        $student = $this->makeEnrolledInBatch($batch, $staff, 'Student A', $mobile, $roll);

        return [$staff, $batch, $student];
    }

    protected function makeEnrolledInBatch(
        Batch $batch,
        User $staff,
        string $name,
        string $mobile,
        string $roll,
    ): Student {
        $student = Student::query()->create([
            'name' => $name,
            'mobile' => $mobile,
            'gender' => Gender::Male,
            'status' => StudentStatus::Enrolled,
            'lead_source' => LeadSource::WalkIn,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'course_id' => $batch->course_id,
            'enquiry_number' => 'ENQ-'.$roll,
            'lead_source' => LeadSource::WalkIn,
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-'.$roll,
            'status' => \App\Enums\AdmissionStatus::Approved,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $batch->course_id,
            'academic_session_id' => $batch->academic_session_id,
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

        return $student;
    }
}
