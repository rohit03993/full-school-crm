<?php

namespace Tests\Feature;

use App\Enums\MetaWhatsAppMessageStatus;
use App\Models\MetaWhatsAppMessage;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class MetaWhatsAppWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_verification_returns_challenge_when_token_matches(): void
    {
        Setting::setValue('meta_whatsapp.verify_token', 'crm-verify-token', 'meta_whatsapp');

        $response = $this->get('/webhooks/meta/whatsapp?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'crm-verify-token',
            'hub_challenge' => '1234567890',
        ]));

        $response->assertOk();
        $response->assertSee('1234567890');
    }

    public function test_webhook_verification_rejects_invalid_token(): void
    {
        Setting::setValue('meta_whatsapp.verify_token', 'crm-verify-token', 'meta_whatsapp');

        $this->get('/webhooks/meta/whatsapp?'.http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong-token',
            'hub_challenge' => '1234567890',
        ]))->assertForbidden();
    }

    public function test_webhook_updates_delivery_status_for_logged_message(): void
    {
        Setting::setValue('meta_whatsapp.app_secret', Crypt::encryptString('meta-app-secret'), 'meta_whatsapp');

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.STATUS123',
            'direction' => 'outbound',
            'phone' => '919811223344',
            'status' => MetaWhatsAppMessageStatus::Sent->value,
            'body_preview' => 'test',
            'status_at' => now(),
        ]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'statuses' => [[
                            'id' => 'wamid.STATUS123',
                            'status' => 'delivered',
                            'timestamp' => '1710000000',
                            'recipient_id' => '919811223344',
                        ]],
                    ],
                ]],
            ]],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = 'sha256='.hash_hmac('sha256', $body, 'meta-app-secret');

        $response = $this->call(
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
        );

        $response->assertOk();

        $this->assertDatabaseHas('meta_whatsapp_messages', [
            'wamid' => 'wamid.STATUS123',
            'status' => MetaWhatsAppMessageStatus::Delivered->value,
        ]);
    }
}
