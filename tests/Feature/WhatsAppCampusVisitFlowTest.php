<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Setting;
use App\Models\User;
use App\Models\Visit;
use App\Services\EnquiryService;
use App\Services\WhatsAppFlowService;
use App\Support\WhatsAppCampusVisitFlowJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppCampusVisitFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_typing_visit_sends_the_form_button(): void
    {
        $this->configureWhatsApp();
        $this->createEnquiry('9876503333');

        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.FORM1']],
            ], 200),
        ]);

        $this->postWebhook($this->textPayload('wamid.VISIT1', '919876503333', 'visit'));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://graph.facebook.com/v20.0/1234567890/messages'
                && data_get($request->data(), 'interactive.type') === 'flow'
                && data_get($request->data(), 'interactive.action.parameters.flow_id') === '555'
                && data_get($request->data(), 'interactive.action.parameters.flow_cta') === 'Book visit';
        });
    }

    public function test_finished_form_saves_one_campus_visit(): void
    {
        $this->configureWhatsApp();
        $course = $this->createEnquiry('9876504444');

        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.THANKS']],
            ], 200),
        ]);

        $payload = $this->flowReplyPayload('wamid.DONE1', '919876504444', [
            'flow_token' => 'campus_visit',
            'course' => (string) $course->id,
            'visit_date' => '2026-10-20',
            'note' => 'Morning slot',
        ]);

        $this->postWebhook($payload);
        $this->postWebhook($payload);

        $visits = Visit::query()->where('remarks', 'whatsapp_flow:wamid.DONE1')->get();

        $this->assertCount(1, $visits);
        $this->assertStringContainsString($course->name, (string) $visits->first()->discussion_summary);
        $this->assertStringContainsString('Morning slot', (string) $visits->first()->discussion_summary);
        $this->assertSame('2026-10-20', $visits->first()->visit_date->toDateString());

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://graph.facebook.com/v20.0/1234567890/messages'
                && data_get($request->data(), 'type') === 'text'
                && str_contains((string) data_get($request->data(), 'text.body'), 'campus visit is noted');
        });
    }

    public function test_publish_creates_the_form_when_meta_has_none(): void
    {
        $this->configureWhatsApp();
        $course = Course::query()->create([
            'name' => 'Class 9 Science',
            'code' => 'C9-SCI',
            'programme_category' => 'coaching',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 1000,
            'status' => CourseStatus::Active,
        ]);

        Http::fake([
            'https://graph.facebook.com/v20.0/waba-1/flows' => Http::sequence()
                ->push(['data' => []], 200)
                ->push(['id' => '42'], 200),
            'https://graph.facebook.com/v20.0/42/assets' => Http::response([
                'success' => true,
                'validation_errors' => [],
            ], 200),
            'https://graph.facebook.com/v20.0/42/publish' => Http::response(['success' => true], 200),
        ]);

        $result = app(WhatsAppFlowService::class)->publishCampusVisit();

        $this->assertSame('success', $result['status']);
        $this->assertSame('42', $result['flow_id']);
        $this->assertSame('42', Setting::getValue(WhatsAppFlowService::FLOW_ID_SETTING));

        Http::assertSent(function ($request) use ($course): bool {
            return str_contains($request->url(), '/42/assets')
                && str_contains($request->body(), $course->name)
                && str_contains($request->body(), WhatsAppCampusVisitFlowJson::SCREEN_ID);
        });
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
