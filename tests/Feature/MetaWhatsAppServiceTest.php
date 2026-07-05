<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\MetaWhatsAppService;
use App\Services\MetaWhatsAppTemplateSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaWhatsAppServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_body_params_pads_blank_slots(): void
    {
        $params = MetaWhatsAppService::normalizeBodyParams(['Rohit', '123'], 4);

        $this->assertSame(['Rohit', '123', '—', '—'], $params);
    }

    public function test_send_posts_meta_template_payload(): void
    {
        config([
            'meta_whatsapp.graph_version' => 'v20.0',
            'meta_whatsapp.phone_number_id' => '1234567890',
            'meta_whatsapp.access_token' => 'meta-test-token',
        ]);

        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.TEST123']],
            ], 200),
        ]);

        $result = app(MetaWhatsAppService::class)->sendTemplate(
            '9811223344',
            'parent_checkin',
            ['Rohit', 'Class 10A', '09:15 AM'],
            'en',
            3,
        );

        $this->assertSame('success', $result['status']);
        $this->assertSame('wamid.TEST123', $result['message_id']);
        $this->assertDatabaseHas('meta_whatsapp_messages', [
            'wamid' => 'wamid.TEST123',
            'status' => 'sent',
            'template_name' => 'parent_checkin',
        ]);

        Http::assertSent(function ($request): bool {
            $json = $request->data();

            return $request->url() === 'https://graph.facebook.com/v20.0/1234567890/messages'
                && $json['to'] === '919811223344'
                && $json['template']['name'] === 'parent_checkin'
                && $json['template']['language']['code'] === 'en'
                && $json['template']['components'][0]['parameters'][0]['text'] === 'Rohit';
        });
    }

    public function test_validate_connection_reads_phone_number_fields(): void
    {
        config([
            'meta_whatsapp.graph_version' => 'v20.0',
            'meta_whatsapp.phone_number_id' => '1234567890',
            'meta_whatsapp.access_token' => 'meta-test-token',
        ]);

        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890*' => Http::response([
                'display_phone_number' => '+91 98765 43210',
                'verified_name' => 'Demo School',
            ], 200),
        ]);

        $result = app(MetaWhatsAppService::class)->validateConnection();

        $this->assertSame('success', $result['status']);
        $this->assertStringContainsString('Demo School', $result['message']);
    }

    public function test_settings_override_env_credentials(): void
    {
        config([
            'meta_whatsapp.phone_number_id' => 'env-phone',
            'meta_whatsapp.access_token' => 'env-token',
        ]);

        Setting::setValue('meta_whatsapp.phone_number_id', 'db-phone', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('db-token'), 'meta_whatsapp');

        $service = app(MetaWhatsAppService::class);

        $this->assertSame('db-phone', $service->phoneNumberId());
        $this->assertSame('db-token', $service->accessToken());
    }

    public function test_template_sync_stores_approved_meta_templates(): void
    {
        config([
            'meta_whatsapp.graph_version' => 'v20.0',
            'meta_whatsapp.waba_id' => 'waba-1',
            'meta_whatsapp.access_token' => 'meta-test-token',
        ]);

        Http::fake([
            'https://graph.facebook.com/v20.0/waba-1/message_templates*' => Http::response([
                'data' => [
                    [
                        'name' => 'parent_checkin',
                        'language' => 'en',
                        'status' => 'APPROVED',
                        'components' => [
                            ['type' => 'BODY', 'text' => 'Hello {{1}}, checked in at {{2}}.'],
                        ],
                    ],
                    [
                        'name' => 'draft_template',
                        'language' => 'en',
                        'status' => 'PENDING',
                        'components' => [],
                    ],
                ],
            ], 200),
        ]);

        $result = app(MetaWhatsAppTemplateSyncService::class)->sync();

        $this->assertSame('success', $result['status']);
        $this->assertSame(1, $result['synced']);
        $this->assertDatabaseHas('meta_whatsapp_templates', [
            'name' => 'parent_checkin',
            'language' => 'en',
            'param_count' => 2,
        ]);
        $this->assertDatabaseMissing('meta_whatsapp_templates', [
            'name' => 'draft_template',
        ]);
    }
}
