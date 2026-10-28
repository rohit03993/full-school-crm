<?php

namespace Tests\Feature;

use App\Enums\WhatsAppProvider;
use App\Models\MetaWhatsAppMessage;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Services\WhatsAppDispatchService;
use App\Support\MetaWhatsAppFailureReason;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_not_configured_when_meta_is_off(): void
    {
        $result = app(\App\Services\WhatsAppDispatchService::class)->send(
            '9811223344',
            ['Rohit'],
            'parent_checkin',
            'Rohit',
            1,
        );

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('not set up', strtolower((string) $result['error']));
    }

    public function test_uses_meta_when_meta_is_enabled_and_configured(): void
    {
        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token'), 'meta_whatsapp');

        MetaWhatsAppTemplate::query()->create([
            'name' => 'parent_checkin',
            'language' => 'en',
            'status' => 'APPROVED',
            'param_count' => 1,
            'body' => 'Hello {{1}}',
            'is_active' => true,
            'synced_at' => now(),
        ]);

        config(['meta_whatsapp.graph_version' => 'v20.0']);

        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.DISPATCH1']],
            ], 200),
        ]);

        $result = app(WhatsAppDispatchService::class)->send(
            '9811223344',
            ['Rohit'],
            'parent_checkin',
            'Rohit',
            1,
        );

        $this->assertSame('success', $result['status']);
        $this->assertSame(WhatsAppProvider::Meta->value, $result['provider']);
        $this->assertSame('wamid.DISPATCH1', $result['message_id']);
    }

    public function test_meta_send_fails_when_template_not_synced(): void
    {
        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token'), 'meta_whatsapp');

        $result = app(WhatsAppDispatchService::class)->send(
            '9811223344',
            ['Rohit'],
            'missing_template',
            'Rohit',
            1,
        );

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('not synced', (string) $result['error']);
    }

    public function test_marketing_template_is_not_sent_again_after_the_ecosystem_limit(): void
    {
        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token'), 'meta_whatsapp');

        MetaWhatsAppTemplate::query()->create([
            'name' => 'student_birthday',
            'language' => 'en',
            'status' => 'APPROVED',
            'param_count' => 1,
            'body' => 'Hello {{1}}',
            'provider_meta' => ['category' => 'MARKETING'],
            'is_active' => true,
            'synced_at' => now(),
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.LIMIT',
            'direction' => 'outbound',
            'phone' => '919811223344',
            'template_name' => 'student_birthday',
            'conversation_category' => 'MARKETING',
            'status' => 'failed',
            'status_detail' => 'This message was not delivered to maintain healthy ecosystem engagement.',
            'status_at' => now(),
        ]);

        Http::fake();

        $result = app(WhatsAppDispatchService::class)->send(
            '9811223344',
            ['Rohit'],
            'student_birthday',
            'Rohit',
            1,
        );

        $this->assertSame('failed', $result['status']);
        $this->assertSame(MetaWhatsAppFailureReason::HOLD_MESSAGE, $result['error']);
        Http::assertNothingSent();
    }
}
