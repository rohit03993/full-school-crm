<?php

namespace Tests\Feature;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Pages\StudentProfilePage;
use App\Models\MetaWhatsAppMessage;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\MetaWhatsAppMediaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentProfileMessagesTabTest extends TestCase
{
    use RefreshDatabase;

    public function test_messages_tab_renders_without_error(): void
    {
        $admin = $this->createSuperAdmin();

        $student = Student::query()->create([
            'name' => 'Kapil',
            'mobile' => '8320936488',
            'status' => StudentStatus::Enquiry,
        ]);

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->set('profileTab', 'messages')
            ->assertSet('profileTab', 'messages')
            ->assertStatus(200);
    }

    public function test_messages_tab_via_query_string_mounts(): void
    {
        $admin = $this->createSuperAdmin();

        $student = Student::query()->create([
            'name' => 'Kapil',
            'mobile' => '8320936488',
            'status' => StudentStatus::Enquiry,
        ]);

        $this->actingAs($admin);

        Livewire::withQueryParams(['tab' => 'messages'])
            ->test(StudentProfilePage::class, ['record' => $student])
            ->assertSet('profileTab', 'messages')
            ->assertStatus(200);
    }

    public function test_messages_tab_survives_missing_meta_messages_table(): void
    {
        $admin = $this->createSuperAdmin();

        $student = Student::query()->create([
            'name' => 'Kapil',
            'mobile' => '8320936488',
            'status' => StudentStatus::Enquiry,
        ]);

        Schema::drop('meta_whatsapp_messages');

        $this->actingAs($admin);

        Livewire::withQueryParams(['tab' => 'messages'])
            ->test(StudentProfilePage::class, ['record' => $student])
            ->assertSet('profileTab', 'messages')
            ->assertSet('messageThread', [])
            ->assertStatus(200);
    }

    public function test_overview_tab_does_not_load_messages_in_background(): void
    {
        Http::fake();

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
            'status_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->assertSet('profileTab', 'overview')
            ->assertSet('messagesTabLoaded', false)
            ->assertStatus(200);
    }

    public function test_messages_tab_with_inbound_parent_image_loads_without_file_upload_field(): void
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
            'status_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->set('profileTab', 'messages')
            ->assertSet('metaSessionOpen', true)
            ->assertSee('Type a message')
            ->assertSee('Attach photo or file')
            ->assertSee('crm-wa-bubble__media-pending', false)
            ->assertDontSee('wire:model="metaReplyAttachment"', false)
            ->assertStatus(200);
    }

    public function test_messages_tab_via_query_string_with_inbound_image(): void
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
            'status' => 'received',
            'status_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($admin);

        Livewire::withQueryParams(['tab' => 'messages'])
            ->test(StudentProfilePage::class, ['record' => $student])
            ->assertSet('profileTab', 'messages')
            ->assertSee('See this')
            ->assertStatus(200);
    }

    public function test_messages_tab_can_send_image_attachment(): void
    {
        Storage::fake(MetaWhatsAppMediaService::DISK);

        Http::fake([
            'https://graph.facebook.com/*' => Http::sequence()
                ->push(['id' => 'uploaded-media-123'])
                ->push(['messages' => [['id' => 'wamid.OUTIMG']]], 200),
        ]);

        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token'), 'meta_whatsapp');

        $admin = $this->createSuperAdmin();

        $student = Student::query()->create([
            'name' => 'Kapil',
            'mobile' => '8320936488',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.IN1',
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '918320936488',
            'student_id' => $student->id,
            'body_preview' => 'Hi',
            'message_type' => 'text',
            'status' => 'received',
            'status_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($admin);

        Livewire::test(StudentProfilePage::class, ['record' => $student])
            ->set('profileTab', 'messages')
            ->set('showMetaReplyAttachment', true)
            ->set('metaReplyAttachment', UploadedFile::fake()->image('favicon pd.png'))
            ->call('sendMetaMedia')
            ->assertStatus(200);

        $this->assertDatabaseHas('meta_whatsapp_messages', [
            'wamid' => 'wamid.OUTIMG',
            'student_id' => $student->id,
            'message_type' => 'image',
        ]);
    }

    public function test_messages_blade_requires_template_id_in_view_data(): void
    {
        $student = Student::query()->create([
            'name' => 'Kapil',
            'mobile' => '8320936488',
            'status' => StudentStatus::Enquiry,
        ]);

        $html = view('filament.pages.partials.student-profile-messages', [
            'record' => $student,
            'messagesTabLoaded' => true,
            'messageThread' => [],
            'metaSessionOpen' => false,
            'metaRoutingActive' => false,
            'whatsappProviderLabel' => 'Meta WhatsApp',
            'metaReplyText' => '',
            'showMetaReplyAttachment' => false,
            'waTemplates' => collect(),
            'waTemplateSyncHint' => null,
            'sendWhatsAppTemplateId' => null,
            'sendWhatsAppTemplateFields' => [],
            'sendWhatsAppTemplateParamCount' => 0,
            'sendWhatsAppTemplatePreview' => null,
            'sendWhatsAppSelectedTemplateName' => null,
        ])->render();

        $this->assertStringContainsString('Choose template', $html);
    }

    protected function createSuperAdmin(): User
    {
        $role = Role::findOrCreate(RoleName::SuperAdmin->value);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
