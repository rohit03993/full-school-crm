<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\WhatsAppTemplate;
use App\Services\PalDigitalWhatsAppService;
use App\Services\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PalDigitalWhatsAppServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_template_params_pads_blank_slots(): void
    {
        $params = PalDigitalWhatsAppService::normalizeTemplateParams(['Rohit', '123'], 4);

        $this->assertSame(['Rohit', '123', '—', '—'], $params);
    }

    public function test_send_posts_waservice_compatible_payload(): void
    {
        config([
            'services.pal_digital.api_key' => 'wsk.550e8400-e29b-41d4-a716-446655440000.secretpart',
            'services.pal_digital.api_url' => 'https://wa.paldigital.in/api/v1/campaign/t1/api/v2',
        ]);

        Http::fake([
            'https://wa.paldigital.in/api/v1/campaign/t1/api/v2' => Http::response([
                'success' => true,
                'message' => 'Campaign triggered successfully',
            ], 200),
        ]);

        $result = app(PalDigitalWhatsAppService::class)->send(
            '9811223344',
            ['Rohit', 'Demo School'],
            'test_announcement',
            null,
            2,
        );

        $this->assertSame('success', $result['status']);

        Http::assertSent(function ($request): bool {
            $json = $request->data();

            return $request->url() === 'https://wa.paldigital.in/api/v1/campaign/t1/api/v2'
                && str_starts_with($json['apiKey'], 'wsk.')
                && $json['campaignName'] === 'test_announcement'
                && $json['destination'] === '919811223344'
                && $json['templateParams'] === ['Rohit', 'Demo School'];
        });
    }

    public function test_api_v1_base_url_is_normalized_to_campaign_endpoint(): void
    {
        config([
            'services.pal_digital.api_url' => 'https://wa.paldigital.in/api/v1',
        ]);

        $this->assertSame(
            'https://wa.paldigital.in/api/v1/campaign/t1/api/v2',
            app(PalDigitalWhatsAppService::class)->apiUrl(),
        );
    }

    public function test_settings_override_env_api_credentials(): void
    {
        config([
            'services.pal_digital.api_key' => 'env-key',
            'services.pal_digital.api_url' => 'https://env.example/send',
        ]);

        Setting::setValue('pal_digital.api_key', 'wsk.test.key', 'whatsapp');
        Setting::setValue('pal_digital.api_url', 'https://db.example/api/v1', 'whatsapp');

        $service = app(PalDigitalWhatsAppService::class);

        $this->assertSame('wsk.test.key', $service->apiKey());
        $this->assertSame('https://db.example/api/v1/campaign/t1/api/v2', $service->apiUrl());
    }

    public function test_parse_fastapi_detail_error_from_waservice(): void
    {
        config([
            'services.pal_digital.api_key' => 'wsk.test.key',
            'services.pal_digital.api_url' => 'https://wa.paldigital.in/api/v1/campaign/t1/api/v2',
        ]);

        Http::fake([
            'https://wa.paldigital.in/api/v1/campaign/t1/api/v2' => Http::response([
                'detail' => "No live API campaign found for campaignName 'missing'.",
            ], 404),
        ]);

        $result = app(PalDigitalWhatsAppService::class)->send('9811223344', ['A'], 'missing');

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('No live API campaign found', (string) $result['error']);
    }

    public function test_fetch_api_campaigns_uses_integration_key_header(): void
    {
        config([
            'services.pal_digital.api_key' => 'wsk.test.key',
            'services.pal_digital.api_url' => 'https://wa.paldigital.in/api/v1',
        ]);

        Http::fake([
            'https://wa.paldigital.in/api/v1/integrations/api-campaigns*' => Http::response([
                ['name' => 'Welcome message', 'param_count' => 2],
            ], 200),
        ]);

        $result = app(PalDigitalWhatsAppService::class)->fetchApiCampaigns();

        $this->assertSame('success', $result['status']);
        $this->assertCount(1, $result['items'] ?? []);

        Http::assertSent(function ($request): bool {
            return $request->header('X-Integration-Key')[0] === 'wsk.test.key';
        });
    }

    public function test_save_credentials_does_not_clear_existing_api_key_when_blank(): void
    {
        Setting::setValue('pal_digital.api_key', 'wsk.existing.key', 'whatsapp');

        $result = app(WhatsAppSettingsService::class)->saveCredentials([
            'pal_digital_api_key' => '',
            'pal_digital_api_url' => 'https://wa.paldigital.in/api/v1',
        ]);

        $this->assertTrue($result['ok']);
        $this->assertSame('wsk.existing.key', app(PalDigitalWhatsAppService::class)->apiKey());
    }

    public function test_save_credentials_rejects_non_waservice_key(): void
    {
        $result = app(WhatsAppSettingsService::class)->saveCredentials([
            'pal_digital_api_key' => 'admin123',
            'pal_digital_api_url' => 'https://wa.paldigital.in/api/v1',
        ]);

        $this->assertFalse($result['ok']);
        $this->assertNull(app(PalDigitalWhatsAppService::class)->apiKey());
    }

    public function test_save_credentials_ignores_invalid_key_field_when_valid_key_stored_and_not_strict(): void
    {
        Setting::setValue('pal_digital.api_key', 'wsk.existing.key', 'whatsapp');

        $result = app(WhatsAppSettingsService::class)->saveCredentials([
            'pal_digital_api_key' => 'admin123',
            'pal_digital_api_url' => 'https://wa.paldigital.in/api/v1',
        ], strictKey: false);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['ignored_invalid_key_field']);
        $this->assertSame('wsk.existing.key', app(PalDigitalWhatsAppService::class)->apiKey());
    }

    public function test_save_settings_ignores_invalid_replace_field_when_valid_key_stored(): void
    {
        Setting::setValue('pal_digital.api_key', 'wsk.existing.key', 'whatsapp');

        $result = app(WhatsAppSettingsService::class)->save([
            'pal_digital_api_key' => 'admin123',
            'pal_digital_api_url' => 'https://wa.paldigital.in/api/v1',
            'postcall_autosend_enabled' => false,
            'attendance_autosend_enabled' => true,
            'attendance_autosend_template_id' => '',
            'campaign_batch_size' => 15,
            'campaign_batch_delay_seconds' => 2,
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['ignored_invalid_key_field']);
        $this->assertSame('15', Setting::getValue('whatsapp.campaign_batch_size'));
        $this->assertSame('1', Setting::getValue('whatsapp.attendance_autosend_enabled'));
    }

    public function test_template_param_resolver_builds_preview_message(): void
    {
        $template = WhatsAppTemplate::query()->create([
            'name' => 'welcome',
            'param_count' => 2,
            'param_mappings' => ['student.name', 'institute.name'],
            'body' => 'Hello {{1}}, welcome to {{2}}.',
        ]);

        $preview = app(\App\Services\WhatsAppTemplateParamResolver::class)->buildPreview(
            $template->body,
            ['Rohit', 'Demo Institute'],
        );

        $this->assertSame('Hello Rohit, welcome to Demo Institute.', $preview);
    }
}
