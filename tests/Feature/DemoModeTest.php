<?php

namespace Tests\Feature;

use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Services\MetaWhatsAppService;
use App\Support\DemoMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token'), 'meta_whatsapp');
        config(['meta_whatsapp.graph_version' => 'v20.0']);
    }

    public function test_demo_mode_defaults_off(): void
    {
        config(['institute.demo_mode' => false]);

        $this->assertFalse(DemoMode::enabled());
    }

    public function test_blocks_template_send_without_calling_meta(): void
    {
        config(['institute.demo_mode' => true]);

        Http::fake();

        MetaWhatsAppTemplate::query()->create([
            'name' => 'parent_checkin',
            'language' => 'en',
            'status' => 'APPROVED',
            'param_count' => 1,
            'body' => 'Hello {{1}}',
            'is_active' => true,
            'synced_at' => now(),
        ]);

        $result = app(MetaWhatsAppService::class)->sendTemplate(
            '9811223344',
            'parent_checkin',
            ['Rohit'],
            'en',
            1,
        );

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('Demo desk', (string) $result['error']);
        Http::assertNothingSent();
    }

    public function test_blocks_text_send_without_calling_meta(): void
    {
        config(['institute.demo_mode' => true]);

        Http::fake();

        $result = app(MetaWhatsAppService::class)->sendText('9811223344', 'Hello');

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('not sent to real phones', (string) $result['error']);
        Http::assertNothingSent();
    }

    public function test_allows_send_when_demo_mode_off(): void
    {
        config(['institute.demo_mode' => false]);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.DEMO_OFF']],
            ], 200),
        ]);

        $result = app(MetaWhatsAppService::class)->sendText('9811223344', 'Hello');

        $this->assertSame('success', $result['status']);
        Http::assertSentCount(1);
    }
}
