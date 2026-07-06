<?php

namespace Tests\Feature;

use App\Enums\WhatsAppLiveCampaignStatus;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Models\WhatsAppLiveCampaign;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppIntegrationApiService;
use App\Services\WhatsAppLiveCampaignService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WhatsAppLiveCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'meta_whatsapp.graph_version' => 'v20.0',
            'meta_whatsapp.phone_number_id' => '1234567890',
            'meta_whatsapp.access_token' => 'meta-test-token',
            'meta_whatsapp.waba_id' => 'waba-1',
        ]);

        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-test-token'), 'meta_whatsapp');
    }

    public function test_live_campaign_trigger_sends_via_meta(): void
    {
        $metaTemplate = MetaWhatsAppTemplate::query()->create([
            'name' => 'parent_checkin',
            'language' => 'en',
            'status' => 'APPROVED',
            'param_count' => 2,
            'body' => 'Hello {{1}}, checked in at {{2}}.',
            'is_active' => true,
            'synced_at' => now(),
        ]);

        WhatsAppTemplate::query()->create([
            'name' => 'parent_checkin',
            'param_count' => 2,
            'body' => 'Hello {{1}}, checked in at {{2}}.',
            'is_active' => true,
            'synced_at' => now(),
        ]);

        $campaign = WhatsAppLiveCampaign::query()->create([
            'name' => 'punch_in_notify',
            'meta_whatsapp_template_id' => $metaTemplate->id,
            'status' => WhatsAppLiveCampaignStatus::Live,
            'went_live_at' => now(),
        ]);

        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.LIVE123']],
            ], 200),
        ]);

        $result = app(WhatsAppLiveCampaignService::class)->trigger(
            $campaign,
            '9811223344',
            'Rohit Sharma',
            ['Rohit Sharma', '9:15 AM'],
        );

        $this->assertSame('success', $result['status']);
        $this->assertSame('wamid.LIVE123', $result['message_id']);
    }

    public function test_aisensy_api_endpoint_triggers_live_campaign(): void
    {
        $metaTemplate = MetaWhatsAppTemplate::query()->create([
            'name' => 'homework_api',
            'language' => 'en',
            'status' => 'APPROVED',
            'param_count' => 1,
            'body' => 'Hello {{1}}, homework posted.',
            'is_active' => true,
            'synced_at' => now(),
        ]);

        WhatsAppTemplate::query()->create([
            'name' => 'homework_api',
            'param_count' => 1,
            'is_active' => true,
            'synced_at' => now(),
        ]);

        WhatsAppLiveCampaign::query()->create([
            'name' => 'homework_notify',
            'meta_whatsapp_template_id' => $metaTemplate->id,
            'status' => WhatsAppLiveCampaignStatus::Live,
            'went_live_at' => now(),
        ]);

        $apiKey = 'crm.test-uuid.testsecret';
        Setting::setValue('whatsapp.integration_api_key', Crypt::encryptString($apiKey), 'whatsapp');

        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890/messages' => Http::response([
                'messages' => [['id' => 'wamid.API123']],
            ], 200),
        ]);

        $response = $this->postJson('/api/v1/campaign/t1/api/v2', [
            'apiKey' => $apiKey,
            'campaignName' => 'homework_notify',
            'destination' => '919811223344',
            'userName' => 'Rohit Sharma',
            'templateParams' => ['Rohit Sharma'],
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Campaign triggered successfully',
            ]);
    }

    public function test_api_rejects_invalid_key(): void
    {
        Setting::setValue('whatsapp.integration_api_key', Crypt::encryptString('crm.valid.key'), 'whatsapp');

        $response = $this->postJson('/api/v1/campaign/t1/api/v2', [
            'apiKey' => 'crm.wrong.key',
            'campaignName' => 'missing',
            'destination' => '919811223344',
        ]);

        $response->assertUnauthorized();
    }

    public function test_integration_key_generation(): void
    {
        $service = app(WhatsAppIntegrationApiService::class);

        $this->assertFalse($service->hasStoredKey());

        $key = $service->generateKey();

        $this->assertTrue(str_starts_with($key, 'crm.'));
        $this->assertTrue($service->validateKey($key));
    }
}
