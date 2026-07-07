<?php

namespace Tests\Feature;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Pages\WhatsAppInboxPage;
use App\Models\MetaWhatsAppMessage;
use App\Models\Student;
use App\Models\User;
use App\Models\Setting;
use App\Services\MetaWhatsAppMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppInboxPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_selecting_conversation_loads_messages_without_error(): void
    {
        Http::fake();

        $admin = $this->createSuperAdmin();

        $student = Student::query()->create([
            'name' => 'Kapil',
            'mobile' => '8320936486',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.CHAT1',
            'direction' => 'outbound',
            'phone' => '918320936486',
            'student_id' => $student->id,
            'body_preview' => 'Dear Parent, attendance update for Kapil.',
            'message_type' => 'text',
            'status' => 'sent',
            'status_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(WhatsAppInboxPage::class)
            ->call('selectConversation', $student->id)
            ->assertSet('selectedStudentId', $student->id)
            ->assertStatus(200);

        Http::assertNothingSent();
    }

    public function test_selecting_conversation_renders_message_panel(): void
    {
        Http::fake();

        $admin = $this->createSuperAdmin();

        $student = Student::query()->create([
            'name' => 'Kapil',
            'mobile' => '8320936486',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.CHAT1',
            'direction' => 'outbound',
            'phone' => '918320936486',
            'student_id' => $student->id,
            'body_preview' => 'Dear Parent, attendance update for Kapil.',
            'message_type' => 'text',
            'status' => 'sent',
            'status_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(WhatsAppInboxPage::class)
            ->call('selectConversation', $student->id)
            ->assertSet('selectedStudentId', $student->id)
            ->assertSee('Dear Parent, attendance update for Kapil.')
            ->assertStatus(200);

        Http::assertNothingSent();
    }

    public function test_inbound_parent_image_opens_with_reply_composer_in_inbox(): void
    {
        Http::fake();
        Storage::fake(MetaWhatsAppMediaService::DISK);

        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token'), 'meta_whatsapp');

        $admin = $this->createSuperAdmin();

        $student = Student::query()->create([
            'name' => 'Amit Verma',
            'mobile' => '9876543210',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.IMAGEIN',
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919876543210',
            'student_id' => $student->id,
            'body_preview' => 'See this',
            'message_type' => 'image',
            'caption' => 'See this',
            'media_id' => 'media-parent-image',
            'status' => 'received',
            'payload' => [
                'type' => 'image',
                'image' => [
                    'id' => 'media-parent-image',
                    'mime_type' => 'image/jpeg',
                    'caption' => 'See this',
                ],
            ],
            'status_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($admin);

        Livewire::test(WhatsAppInboxPage::class)
            ->call('selectConversation', $student->id)
            ->assertSet('selectedStudentId', $student->id)
            ->assertSet('metaSessionOpen', true)
            ->assertSee('Amit Verma')
            ->assertSee('Quick reply')
            ->assertSee('Open student profile')
            ->assertDontSee('wire:model="metaReplyAttachment"', false)
            ->assertStatus(200);
    }

    public function test_inbound_parent_image_downloads_on_thread_open(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::sequence()
                ->push(['url' => 'https://cdn.example.com/parent.jpg', 'mime_type' => 'image/jpeg'])
                ->push('parent-image-bytes', 200),
            'https://cdn.example.com/*' => Http::response('parent-image-bytes', 200),
        ]);
        Storage::fake(MetaWhatsAppMediaService::DISK);

        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token'), 'meta_whatsapp');

        $admin = $this->createSuperAdmin();

        $student = Student::query()->create([
            'name' => 'Amit Verma',
            'mobile' => '9876543210',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.IMAGEIN3',
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919876543210',
            'student_id' => $student->id,
            'body_preview' => 'See this',
            'message_type' => 'image',
            'caption' => 'See this',
            'media_id' => 'media-parent-image',
            'status' => 'received',
            'payload' => [
                'type' => 'image',
                'image' => [
                    'id' => 'media-parent-image',
                    'mime_type' => 'image/jpeg',
                    'caption' => 'See this',
                ],
            ],
            'status_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($admin);

        Livewire::test(WhatsAppInboxPage::class)
            ->call('selectConversation', $student->id)
            ->assertSee('crm-wa-bubble__image', false)
            ->assertStatus(200);
    }

    public function test_inbound_parent_image_with_stored_file_renders_preview(): void
    {
        Http::fake();
        Storage::fake(MetaWhatsAppMediaService::DISK);
        Storage::disk(MetaWhatsAppMediaService::DISK)->put('whatsapp-media/parent-photo.jpg', 'image-bytes');

        $admin = $this->createSuperAdmin();

        $student = Student::query()->create([
            'name' => 'Amit Verma',
            'mobile' => '9876543210',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.IMAGEIN2',
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919876543210',
            'student_id' => $student->id,
            'body_preview' => 'See this',
            'message_type' => 'image',
            'caption' => 'See this',
            'media_id' => 'media-parent-image',
            'media_path' => 'whatsapp-media/parent-photo.jpg',
            'media_mime_type' => 'image/jpeg',
            'status' => 'received',
            'status_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($admin);

        Livewire::test(WhatsAppInboxPage::class)
            ->call('selectConversation', $student->id)
            ->assertSee('crm-wa-bubble__image', false)
            ->assertStatus(200);
    }

    protected function createSuperAdmin(): User
    {
        $role = Role::findOrCreate(RoleName::SuperAdmin->value);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
