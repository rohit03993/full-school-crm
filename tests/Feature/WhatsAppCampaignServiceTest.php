<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\WhatsAppAudienceType;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\PalDigitalTemplateSyncService;
use App\Services\WhatsAppCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppCampaignServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_batch_audience_includes_only_active_batch_students_with_mobile(): void
    {
        $admin = $this->createSuperAdmin();
        [$course, $batchA, $batchB] = $this->createCourseWithBatches($admin);
        $inBatch = $this->createEnrolledStudent('In Batch', '9876500101', '701', $course, $batchA);
        $otherBatch = $this->createEnrolledStudent('Other Batch', '9876500102', '702', $course, $batchB);
        $noMobile = $this->createEnrolledStudent('No Mobile', '', '703', $course, $batchA);

        $template = $this->createTemplate();

        $campaign = app(WhatsAppCampaignService::class)->createCampaign([
            'name' => 'Batch test',
            'whatsapp_template_id' => $template->id,
            'audience_type' => WhatsAppAudienceType::Batch->value,
            'course_id' => $course->id,
            'batch_id' => $batchA->id,
            'campaign_variables' => [
                'topic' => 'Maths test',
                'subject' => 'Algebra',
                'date' => '20 Jun 2026',
                'time' => '10:00 AM',
            ],
        ], $admin);

        $recipientIds = $campaign->recipients()->pluck('student_id')->all();

        $this->assertSame(1, $campaign->total_recipients);
        $this->assertContains($inBatch->id, $recipientIds);
        $this->assertNotContains($otherBatch->id, $recipientIds);
        $this->assertNotContains($noMobile->id, $recipientIds);
        $this->assertSame('Maths test', $campaign->campaignVariable('topic'));
    }

    public function test_course_audience_includes_all_active_enrollments_in_course(): void
    {
        $admin = $this->createSuperAdmin();
        [$course, $batchA, $batchB] = $this->createCourseWithBatches($admin);
        $studentA = $this->createEnrolledStudent('Course A', '9876500201', '801', $course, $batchA);
        $studentB = $this->createEnrolledStudent('Course B', '9876500202', '802', $course, $batchB);

        $otherCourse = Course::query()->create([
            'name' => 'Class 12',
            'code' => 'CLS-12',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 45000,
            'status' => CourseStatus::Active,
        ]);
        $otherBatch = Batch::query()->create([
            'name' => 'Evening',
            'course_id' => $otherCourse->id,
            'trainer_user_id' => $admin->id,
            'start_date' => '2026-06-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);
        $otherStudent = $this->createEnrolledStudent('Other course', '9876500203', '803', $otherCourse, $otherBatch);

        $template = $this->createTemplate();

        $campaign = app(WhatsAppCampaignService::class)->createCampaign([
            'name' => 'Course test',
            'whatsapp_template_id' => $template->id,
            'audience_type' => WhatsAppAudienceType::Course->value,
            'course_id' => $course->id,
        ], $admin);

        $recipientIds = $campaign->recipients()->pluck('student_id')->sort()->values()->all();

        $this->assertSame(2, $campaign->total_recipients);
        $this->assertSame(
            [$studentA->id, $studentB->id],
            collect($recipientIds)->sort()->values()->all(),
        );
        $this->assertNotContains($otherStudent->id, $recipientIds);
    }

    public function test_param_resolver_uses_batch_and_campaign_variables(): void
    {
        $admin = $this->createSuperAdmin();
        [$course, $batch] = $this->createCourseWithBatches($admin);
        $student = $this->createEnrolledStudent('Resolver Student', '9876500301', '901', $course, $batch);

        $template = WhatsAppTemplate::query()->create([
            'name' => 'Test announcement',
            'param_count' => 6,
            'param_mappings' => [
                'student.name',
                'batch.name',
                'campaign.topic',
                'campaign.subject',
                'campaign.date',
                'campaign.time',
            ],
            'body' => 'Hi {{1}}, batch {{2}}: {{3}} in {{4}} on {{5}} at {{6}}.',
        ]);

        $campaign = app(WhatsAppCampaignService::class)->createCampaign([
            'name' => 'Announcement',
            'whatsapp_template_id' => $template->id,
            'audience_type' => WhatsAppAudienceType::Batch->value,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'campaign_variables' => [
                'topic' => 'Unit test',
                'subject' => 'Physics',
                'date' => '21 Jun 2026',
                'time' => '9:00 AM',
            ],
        ], $admin);

        $params = app(\App\Services\WhatsAppTemplateParamResolver::class)->resolveAll(
            $template->paramSources(),
            $student->fresh(['activeBatchStudent.batch']),
            $admin,
            null,
            $campaign,
        );

        $this->assertSame([
            'Resolver Student',
            $batch->name,
            'Unit test',
            'Physics',
            '21 Jun 2026',
            '9:00 AM',
        ], $params);
    }

    public function test_create_campaign_accepts_manual_template_params_array(): void
    {
        $admin = $this->createSuperAdmin();
        [$course, $batch] = $this->createCourseWithBatches($admin);
        $this->createEnrolledStudent('Manual Params', '9876500401', '1001', $course, $batch);

        $template = WhatsAppTemplate::query()->create([
            'name' => 'parent_attendance',
            'param_count' => 2,
            'param_mappings' => [],
            'provider_meta' => [
                'body_variables' => ['student_name', 'date'],
            ],
        ]);

        $campaign = app(WhatsAppCampaignService::class)->createCampaign([
            'name' => 'Manual params test',
            'whatsapp_template_id' => $template->id,
            'audience_type' => WhatsAppAudienceType::Batch->value,
            'course_id' => $course->id,
            'batch_id' => $batch->id,
            'campaign_variables' => [
                '_manual' => ['Rohit', '20 Jun 2026'],
            ],
        ], $admin);

        $this->assertSame(['Rohit', '20 Jun 2026'], $campaign->campaignVariable('_manual'));
    }

    public function test_template_sync_creates_local_templates_from_waservice_api(): void
    {
        config([
            'services.pal_digital.api_key' => 'wsk.test.key',
            'services.pal_digital.api_url' => 'https://wa.paldigital.in/api/v1/campaign/t1/api/v2',
        ]);

        WhatsAppTemplate::query()->create([
            'name' => 'Old manual template',
            'param_count' => 1,
            'param_mappings' => ['student.name'],
            'provider_meta' => ['source' => 'waservice_manual_register'],
        ]);

        Http::fake([
            'https://wa.paldigital.in/api/v1/integrations/api-campaigns*' => Http::response([
                [
                    'id' => 'camp-1',
                    'name' => 'Test announcement',
                    'status' => 'live',
                    'campaign_type' => 'api',
                    'template_name' => 'test_announcement',
                    'template_language' => 'en',
                    'preview_text' => 'Hello {{1}}, class {{2}} on {{3}}.',
                    'body_variables' => ['name', 'batch', 'date'],
                    'param_count' => 3,
                ],
            ], 200),
        ]);

        $result = app(PalDigitalTemplateSyncService::class)->sync();

        $this->assertSame('success', $result['status']);
        $this->assertSame(1, $result['synced']);
        $this->assertSame(1, $result['removed']);

        $this->assertDatabaseHas('whatsapp_templates', [
            'name' => 'Test announcement',
            'param_count' => 3,
            'body' => 'Hello {{1}}, class {{2}} on {{3}}.',
        ]);

        $this->assertDatabaseMissing('whatsapp_templates', [
            'name' => 'Old manual template',
        ]);

        $template = WhatsAppTemplate::query()->where('name', 'Test announcement')->firstOrFail();
        $this->assertSame(['student.name', 'batch.name', 'campaign.date'], $template->param_mappings);
        $this->assertSame('waservice_api_sync', data_get($template->provider_meta, 'source'));
        $this->assertSame(['name', 'batch', 'date'], data_get($template->provider_meta, 'body_variables'));
    }

    protected function createSuperAdmin(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        return $admin;
    }

    protected function createTemplate(): WhatsAppTemplate
    {
        return WhatsAppTemplate::query()->create([
            'name' => 'Generic template',
            'param_count' => 1,
            'param_mappings' => ['student.name'],
        ]);
    }

    /**
     * @return array{0: Course, 1: Batch, 2: Batch}
     */
    protected function createCourseWithBatches(?User $trainer = null): array
    {
        $trainer ??= $this->createSuperAdmin();

        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 11 Science',
            'code' => 'CLS-11-SCI-WA',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 40000,
            'status' => CourseStatus::Active,
        ]);

        $batchA = Batch::query()->create([
            'name' => 'Morning A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'trainer_user_id' => $trainer->id,
            'start_date' => '2026-06-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        $batchB = Batch::query()->create([
            'name' => 'Morning B',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'trainer_user_id' => $trainer->id,
            'start_date' => '2026-06-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        return [$course, $batchA, $batchB];
    }

    protected function createEnrolledStudent(
        string $name,
        ?string $mobile,
        string $roll,
        Course $course,
        Batch $batch,
    ): Student {
        $session = AcademicSession::query()->firstOrFail();

        $student = Student::query()->create([
            'name' => $name,
            'mobile' => $mobile,
            'status' => StudentStatus::Enrolled,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'ENQ-WA-'.$roll,
            'course_id' => $course->id,
            'lead_source' => LeadSource::BulkImport,
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-WA-'.$roll,
            'course_fee' => 40000,
            'discount_amount' => 0,
            'net_fee' => 40000,
            'use_installment_plan' => false,
            'status' => AdmissionStatus::Approved,
            'approved_at' => now(),
            'submitted_at' => now(),
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
            'assigned_at' => now(),
            'is_active' => true,
            'assigned_by_user_id' => User::query()->first()?->id ?? User::factory()->create()->id,
        ]);

        return $student->fresh(['activeEnrollment', 'activeBatchStudent.batch']);
    }
}
