<?php

namespace Tests\Feature;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\StudentStatus;
use App\Models\MetaWhatsAppMessage;
use App\Models\Setting;
use App\Models\Student;
use App\Services\MetaWhatsAppInboxService;
use App\Services\StudentWhatsAppThreadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaWhatsAppInboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_thread_excludes_sent_campaign_rows_when_meta_log_exists(): void
    {
        $student = Student::query()->create([
            'name' => 'Test Student',
            'mobile' => '9811223344',
            'status' => StudentStatus::Enquiry,
        ]);

        $template = \App\Models\WhatsAppTemplate::query()->create([
            'name' => 'first_try',
            'body' => 'Hi {{1}}',
            'param_count' => 0,
            'is_active' => true,
        ]);

        $campaign = \App\Models\WhatsAppCampaign::query()->create([
            'whatsapp_template_id' => $template->id,
            'name' => 'first_try',
            'status' => \App\Enums\WhatsAppCampaignStatus::Completed,
            'total_recipients' => 1,
        ]);

        \App\Models\WhatsAppCampaignRecipient::query()->create([
            'whatsapp_campaign_id' => $campaign->id,
            'student_id' => $student->id,
            'phone' => '9811223344',
            'status' => \App\Enums\WhatsAppRecipientStatus::Sent,
            'message_sent' => 'first_try',
        ]);

        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '919811223344',
            'student_id' => $student->id,
            'template_name' => 'first_try',
            'body_preview' => 'Hi This is the first message',
            'status' => 'read',
            'status_at' => now(),
        ]);

        $thread = app(StudentWhatsAppThreadService::class)->threadForStudent($student);

        $this->assertCount(1, $thread);
        $this->assertStringContainsString('first message', $thread->first()->body);
    }

    public function test_thread_merges_campaign_and_meta_messages(): void
    {
        $student = Student::query()->create([
            'name' => 'Test Student',
            'mobile' => '9811223344',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919811223344',
            'student_id' => $student->id,
            'body_preview' => 'Hello school',
            'status' => 'received',
            'status_at' => now()->subHour(),
        ]);

        $thread = app(StudentWhatsAppThreadService::class)->threadForStudent($student);

        $this->assertCount(1, $thread);
        $this->assertTrue($thread->first()->isInbound());
    }

    public function test_session_open_when_parent_messaged_within_24_hours(): void
    {
        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token'), 'meta_whatsapp');

        $student = Student::query()->create([
            'name' => 'Test Student',
            'mobile' => '9811223344',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919811223344',
            'student_id' => $student->id,
            'body_preview' => 'Hi',
            'status' => 'received',
            'status_at' => now()->subHours(2),
        ]);

        $this->assertTrue(app(StudentWhatsAppThreadService::class)->sessionOpenForStudent($student));
    }

    public function test_send_reply_uses_meta_text_api(): void
    {
        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token'), 'meta_whatsapp');

        config(['meta_whatsapp.graph_version' => 'v20.0']);

        $student = Student::query()->create([
            'name' => 'Test Student',
            'mobile' => '9811223344',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919811223344',
            'student_id' => $student->id,
            'body_preview' => 'Need fee details',
            'status' => 'received',
            'created_at' => now()->subHour(),
            'status_at' => now()->subHour(),
        ]);

        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.REPLY1']],
            ], 200),
        ]);

        $result = app(MetaWhatsAppInboxService::class)->sendReply($student, 'Fee receipt is on the portal.');

        $this->assertSame('success', $result['status']);
        $this->assertDatabaseHas('meta_whatsapp_messages', [
            'wamid' => 'wamid.REPLY1',
            'body_preview' => 'Fee receipt is on the portal.',
        ]);
    }
}
