<?php

namespace Tests\Feature;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\StudentStatus;
use App\Models\MetaWhatsAppMessage;
use App\Models\Setting;
use App\Models\Student;
use App\Services\MetaWhatsAppMediaService;
use App\Services\MetaWhatsAppWebhookService;
use App\Services\StudentWhatsAppThreadService;
use App\Support\MetaWhatsAppInboundMessageParser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MetaWhatsAppMediaTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_parser_extracts_image_metadata(): void
    {
        $parsed = MetaWhatsAppInboundMessageParser::parse([
            'type' => 'image',
            'image' => [
                'id' => 'media-123',
                'mime_type' => 'image/jpeg',
                'caption' => 'Homework photo',
            ],
        ]);

        $this->assertSame('image', $parsed['message_type']);
        $this->assertSame('media-123', $parsed['media_id']);
        $this->assertSame('Homework photo', $parsed['body_preview']);
        $this->assertSame('Homework photo', $parsed['caption']);
    }

    public function test_webhook_stores_inbound_image_and_downloads_media(): void
    {
        Storage::fake(MetaWhatsAppMediaService::DISK);

        Setting::setValue('meta_whatsapp.app_secret', Crypt::encryptString('meta-app-secret'), 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '123456789', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token'), 'meta_whatsapp');

        Http::fake([
            'https://graph.facebook.com/*' => Http::sequence()
                ->push(['url' => 'https://cdn.example.com/photo.jpg', 'mime_type' => 'image/jpeg'])
                ->push('fake-image-binary', 200),
            'https://cdn.example.com/*' => Http::response('fake-image-binary', 200),
        ]);

        $student = Student::query()->create([
            'name' => 'Kapil',
            'mobile' => '9811223344',
            'status' => StudentStatus::Enquiry,
        ]);

        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [[
                'changes' => [[
                    'value' => [
                        'messages' => [[
                            'from' => '919811223344',
                            'id' => 'wamid.IMAGE123',
                            'timestamp' => '1710000000',
                            'type' => 'image',
                            'image' => [
                                'id' => 'media-123',
                                'mime_type' => 'image/jpeg',
                                'caption' => 'Homework photo',
                            ],
                        ]],
                    ],
                ]],
            ]],
        ];

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

        $message = MetaWhatsAppMessage::query()->where('wamid', 'wamid.IMAGE123')->first();

        $this->assertNotNull($message);
        $this->assertSame('image', $message->message_type);
        $this->assertSame('Homework photo', $message->caption);
        $this->assertNotNull($message->media_path);
        $this->assertTrue(Storage::disk(MetaWhatsAppMediaService::DISK)->exists((string) $message->media_path));

        $thread = app(StudentWhatsAppThreadService::class)->threadForStudent($student);
        $item = $thread->first();

        $this->assertNotNull($item);
        $this->assertSame('image', $item->messageType);
        $this->assertNotNull($item->mediaUrl);
        $this->assertStringContainsString('Homework photo', $item->body);
    }

    public function test_thread_load_syncs_pending_media_when_meta_configured(): void
    {
        Storage::fake(MetaWhatsAppMediaService::DISK);

        Setting::setValue('meta_whatsapp.phone_number_id', '123456789', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token'), 'meta_whatsapp');

        Http::fake([
            'https://graph.facebook.com/*' => Http::sequence()
                ->push(['url' => 'https://cdn.example.com/photo.jpg', 'mime_type' => 'image/jpeg'])
                ->push('fake-image-binary', 200),
            'https://cdn.example.com/*' => Http::response('fake-image-binary', 200),
        ]);

        $student = Student::query()->create([
            'name' => 'Kapil',
            'mobile' => '9811223344',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.PENDINGIMG',
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919811223344',
            'student_id' => $student->id,
            'body_preview' => 'Pending photo',
            'message_type' => 'image',
            'media_id' => 'media-pending',
            'status' => 'received',
            'payload' => [
                'type' => 'image',
                'image' => [
                    'id' => 'media-pending',
                    'mime_type' => 'image/jpeg',
                    'caption' => 'Pending photo',
                ],
            ],
            'status_at' => now(),
        ]);

        $item = app(StudentWhatsAppThreadService::class)
            ->threadForStudent($student)
            ->first();

        $this->assertNotNull($item);
        $this->assertSame('image', $item->messageType);
        $this->assertNotNull($item->mediaUrl);
        $this->assertFalse($item->mediaPending);
        $this->assertStringContainsString('Pending photo', $item->body);
    }

    public function test_thread_load_skips_meta_download_when_not_configured(): void
    {
        Http::fake();

        $student = Student::query()->create([
            'name' => 'Kapil',
            'mobile' => '9811223344',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.PENDINGIMG',
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919811223344',
            'student_id' => $student->id,
            'body_preview' => 'Pending photo',
            'message_type' => 'image',
            'media_id' => 'media-pending',
            'status' => 'received',
            'payload' => [
                'type' => 'image',
                'image' => [
                    'id' => 'media-pending',
                    'mime_type' => 'image/jpeg',
                    'caption' => 'Pending photo',
                ],
            ],
            'status_at' => now(),
        ]);

        $item = app(StudentWhatsAppThreadService::class)
            ->threadForStudent($student)
            ->first();

        $this->assertNotNull($item);
        $this->assertSame('image', $item->messageType);
        $this->assertNull($item->mediaUrl);
        $this->assertTrue($item->mediaPending);
        Http::assertNothingSent();
    }

    public function test_text_message_with_emoji_keeps_body(): void
    {
        $parsed = MetaWhatsAppInboundMessageParser::parse([
            'type' => 'text',
            'text' => ['body' => 'Thanks 🙂👍'],
        ]);

        $this->assertSame('text', $parsed['message_type']);
        $this->assertSame('Thanks 🙂👍', $parsed['body_preview']);
    }

    public function test_thread_exposes_stored_outbound_image_url(): void
    {
        Storage::fake(MetaWhatsAppMediaService::DISK);
        Storage::disk(MetaWhatsAppMediaService::DISK)->put('whatsapp-media/out-test.jpg', 'image-data');

        $student = Student::query()->create([
            'name' => 'Kapil',
            'mobile' => '9811223344',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.OUTIMG',
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '919811223344',
            'student_id' => $student->id,
            'body_preview' => '📷 Photo',
            'message_type' => 'image',
            'media_path' => 'whatsapp-media/out-test.jpg',
            'media_mime_type' => 'image/jpeg',
            'media_filename' => 'notice.jpg',
            'status' => 'sent',
            'status_at' => now(),
        ]);

        $item = app(StudentWhatsAppThreadService::class)
            ->threadForStudent($student)
            ->first()
            ?->toArray();

        $this->assertNotNull($item);
        $this->assertSame('image', $item['messageType']);
        $this->assertNotNull($item['mediaUrl']);
    }
}
