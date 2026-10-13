<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\WhatsAppInchatAudience;
use App\Enums\WhatsAppInchatFieldType;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visit;
use App\Models\WhatsAppInchatForm;
use App\Models\WhatsAppInchatFormField;
use App\Services\EnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppInchatFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visiting_lead_gets_the_lead_form_for_the_same_word(): void
    {
        $this->configureWhatsApp();
        $course = $this->createEnquiry('9876505555');
        $leadForm = $this->makeForm(WhatsAppInchatAudience::VisitingLead, 'fees', '701', 'Fee help');
        $this->makeForm(WhatsAppInchatAudience::ExistingStudent, 'fees', '702', 'Student fees');

        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.FORMLEAD']],
            ], 200),
        ]);

        $this->postWebhook($this->textPayload('wamid.FEES1', '919876505555', 'fees'));

        Http::assertSent(function ($request): bool {
            return data_get($request->data(), 'interactive.action.parameters.flow_id') === '701';
        });

        $this->assertNotSame('702', (string) data_get(Http::recorded()[0][0]->data(), 'interactive.action.parameters.flow_id'));
        $this->assertSame('Fee help', $leadForm->fresh()->name);
        $this->assertNotNull($course->id);
    }

    public function test_an_existing_student_gets_the_student_form(): void
    {
        $this->configureWhatsApp();
        $course = $this->createEnquiry('9876506666');
        $enquiry = \App\Models\Enquiry::query()->first();
        $this->enroll($enquiry->student_id, $enquiry->id, $course->id, '9876506666');
        $this->makeForm(WhatsAppInchatAudience::VisitingLead, 'fees', '701', 'Fee help');
        $this->makeForm(WhatsAppInchatAudience::ExistingStudent, 'fees', '702', 'Student fees');

        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.FORMSTUDENT']],
            ], 200),
        ]);

        $this->postWebhook($this->textPayload('wamid.FEES2', '919876506666', 'fees'));

        Http::assertSent(function ($request): bool {
            return data_get($request->data(), 'interactive.action.parameters.flow_id') === '702';
        });
    }

    public function test_finished_form_shows_in_the_inbox_and_does_not_add_a_visit(): void
    {
        $this->configureWhatsApp();
        $this->createEnquiry('9876507777');
        $form = $this->makeForm(WhatsAppInchatAudience::VisitingLead, 'fees', '701', 'Fee help');
        $field = WhatsAppInchatFormField::query()->where('whatsapp_inchat_form_id', $form->id)->first();
        $visitsBefore = Visit::query()->count();

        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.THANKSFORM']],
            ], 200),
        ]);

        $payload = $this->flowReplyPayload('wamid.DONEFORM', '919876507777', [
            'flow_token' => $form->flow_token,
            $field->answerKey() => 'Need the receipt',
        ]);

        $this->postWebhook($payload);
        $this->postWebhook($payload);

        $this->assertSame($visitsBefore, Visit::query()->count());
        $this->assertDatabaseHas('meta_whatsapp_messages', [
            'wamid' => 'wamid.DONEFORM',
            'body_preview' => "Fee help\nReceipt note: Need the receipt",
        ]);

        Http::assertSentCount(1);
    }

    public function test_a_new_number_gets_the_education_form_and_a_student_in_class_does_not(): void
    {
        $this->configureWhatsApp();
        $this->makeForm(WhatsAppInchatAudience::EducationLead, 'admission', '801', 'Admission enquiry');

        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.ADMISSION']],
            ], 200),
        ]);

        $this->postWebhook($this->textPayload('wamid.NEW1', '919876508888', 'admission'));

        Http::assertSent(function ($request): bool {
            return data_get($request->data(), 'interactive.action.parameters.flow_id') === '801';
        });

        $course = $this->createEnquiry('9876509999');
        $enquiry = \App\Models\Enquiry::query()->whereHas('student', fn ($query) => $query->where('mobile', '9876509999'))->first();
        $this->enroll($enquiry->student_id, $enquiry->id, $course->id, '9876509999');

        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.SHOULDNOT']],
            ], 200),
        ]);

        $this->postWebhook($this->textPayload('wamid.ENROLLED1', '919876509999', 'admission'));

        Http::assertNothingSent();
    }

    public function test_a_finished_education_form_creates_one_enquiry(): void
    {
        $this->configureWhatsApp();
        $form = $this->makeForm(WhatsAppInchatAudience::EducationLead, 'admission', '801', 'Admission enquiry');
        $field = WhatsAppInchatFormField::query()->where('whatsapp_inchat_form_id', $form->id)->first();

        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.LEADTHANKS']],
            ], 200),
        ]);

        $payload = $this->flowReplyPayload('wamid.LEAD1', '919876501010', [
            'flow_token' => $form->flow_token,
            $field->answerKey() => 'Aarav Sharma',
        ]);

        $this->postWebhook($payload);
        $this->postWebhook($payload);

        $this->assertDatabaseCount('enquiries', 1);
        $this->assertDatabaseHas('students', [
            'name' => 'Aarav Sharma',
            'mobile' => '9876501010',
        ]);
        $this->assertDatabaseHas('enquiries', [
            'lead_source' => 'whatsapp',
        ]);
    }

    public function test_an_existing_enquiry_is_not_created_again(): void
    {
        $this->configureWhatsApp();
        $this->createEnquiry('9876502020');
        $form = $this->makeForm(WhatsAppInchatAudience::EducationLead, 'admission', '801', 'Admission enquiry');
        $field = WhatsAppInchatFormField::query()->where('whatsapp_inchat_form_id', $form->id)->first();

        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.LEADAGAIN']],
            ], 200),
        ]);

        $this->postWebhook($this->flowReplyPayload('wamid.LEAD2', '919876502020', [
            'flow_token' => $form->flow_token,
            $field->answerKey() => 'Aarav Sharma',
        ]));

        $this->assertDatabaseCount('enquiries', 1);
        $this->assertDatabaseHas('visits', [
            'remarks' => 'whatsapp_lead:wamid.LEAD2',
        ]);
    }

    protected function makeForm(WhatsAppInchatAudience $audience, string $word, string $flowId, string $name): WhatsAppInchatForm
    {
        $form = WhatsAppInchatForm::query()->create([
            'name' => $name,
            'audience' => $audience,
            'trigger_word' => $word,
            'button_label' => 'Open form',
            'enabled' => true,
            'status' => 'published',
            'flow_id' => $flowId,
            'published_at' => now(),
        ]);

        WhatsAppInchatFormField::query()->create([
            'whatsapp_inchat_form_id' => $form->id,
            'sort' => 0,
            'field_type' => WhatsAppInchatFieldType::ShortText,
            'label' => 'Receipt note',
            'is_required' => true,
        ]);

        return $form->fresh();
    }

    protected function enroll(int $studentId, int $enquiryId, int $courseId, string $mobile): void
    {
        $admission = Admission::query()->create([
            'student_id' => $studentId,
            'enquiry_id' => $enquiryId,
            'admission_number' => 'ADM-'.$mobile,
            'status' => AdmissionStatus::Approved,
            'submitted_at' => now(),
        ]);

        Enrollment::query()->create([
            'student_id' => $studentId,
            'admission_id' => $admission->id,
            'course_id' => $courseId,
            'enrollment_number' => 'ROLL-'.$mobile,
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);
    }

    protected function configureWhatsApp(): void
    {
        config([
            'meta_whatsapp.graph_version' => 'v20.0',
            'meta_whatsapp.phone_number_id' => '1234567890',
            'meta_whatsapp.access_token' => 'meta-test-token',
            'meta_whatsapp.waba_id' => 'waba-1',
        ]);

        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.app_secret', Crypt::encryptString('meta-app-secret'), 'meta_whatsapp');
        Setting::setValue('whatsapp.campus_visit_flow_id', '555', 'whatsapp');
    }

    protected function createEnquiry(string $mobile): Course
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole(RoleName::Staff->value);

        $course = Course::query()->create([
            'name' => 'Class 9 Science',
            'code' => 'C9-'.$mobile,
            'programme_category' => 'coaching',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 1000,
            'status' => CourseStatus::Active,
        ]);

        app(EnquiryService::class)->create([
            'name' => 'Parent Test',
            'father_name' => 'Father Test',
            'date_of_birth' => '2012-01-01',
            'gender' => Gender::Male->value,
            'mobile' => $mobile,
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn->value,
            'meeting_with_user_id' => $staff->id,
            'discussion_summary' => 'Walk-in.',
            'visit_status' => 'interested',
        ], $staff, LeadSource::WalkIn);

        return $course;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function postWebhook(array $payload): void
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $body, 'meta-app-secret');

        $this->call(
            'POST',
            '/webhooks/meta/whatsapp',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_HUB_SIGNATURE_256' => $signature,
            ],
            $body,
        )->assertOk();
    }

    /**
     * @return array<string, mixed>
     */
    protected function textPayload(string $wamid, string $from, string $text): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => $from,
                            'id' => $wamid,
                            'type' => 'text',
                            'text' => ['body' => $text],
                        ]],
                    ],
                ]],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    protected function flowReplyPayload(string $wamid, string $from, array $answers): array
    {
        return [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => $from,
                            'id' => $wamid,
                            'type' => 'interactive',
                            'interactive' => [
                                'type' => 'nfm_reply',
                                'nfm_reply' => [
                                    'name' => 'flow',
                                    'body' => 'Sent',
                                    'response_json' => json_encode($answers, JSON_THROW_ON_ERROR),
                                ],
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];
    }
}
