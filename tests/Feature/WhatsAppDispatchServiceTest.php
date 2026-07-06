<?php

namespace Tests\Feature;

use App\Enums\WhatsAppProvider;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Services\WhatsAppDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppDispatchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_pal_digital_when_meta_is_disabled(): void
    {
        config([
            'services.pal_digital.api_key' => 'wsk.550e8400-e29b-41d4-a716-446655440000.secretpart',
            'services.pal_digital.api_url' => 'https://wa.paldigital.in/api/v1/campaign/t1/api/v2',
        ]);

        Http::fake([
            'https://wa.paldigital.in/api/v1/campaign/t1/api/v2' => Http::response(['success' => true], 200),
        ]);

        $result = app(WhatsAppDispatchService::class)->send(
            '9811223344',
            ['Rohit'],
            'parent_checkin',
            'Rohit',
            1,
        );

        $this->assertSame('success', $result['status']);
        $this->assertSame(WhatsAppProvider::PalDigital->value, $result['provider']);
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
}
