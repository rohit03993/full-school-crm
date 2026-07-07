<?php

namespace Tests\Feature;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\StudentStatus;
use App\Models\MetaWhatsAppMessage;
use App\Models\Setting;
use App\Models\Student;
use App\Services\MetaWhatsAppMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SyncWhatsAppMediaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_downloads_missing_inbound_media(): void
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
            'name' => 'Amit Verma',
            'mobile' => '9876543210',
            'status' => StudentStatus::Enquiry,
        ]);

        $message = MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.BACKFILL',
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919876543210',
            'student_id' => $student->id,
            'body_preview' => 'See this',
            'message_type' => 'image',
            'media_id' => 'media-backfill',
            'status' => 'received',
            'status_at' => now(),
        ]);

        Artisan::call('crm:sync-whatsapp-media', ['--student' => $student->id]);

        $message->refresh();

        $this->assertNotNull($message->media_path);
        $this->assertTrue(Storage::disk(MetaWhatsAppMediaService::DISK)->exists((string) $message->media_path));
    }
}
