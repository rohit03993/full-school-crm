<?php

namespace Tests\Feature;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Pages\WhatsAppInboxPage;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Models\MetaWhatsAppMessage;
use App\Models\Student;
use App\Models\User;
use App\Models\Setting;
use App\Models\WhatsAppCampaign;
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
            ->call('selectConversation', '918320936486', $student->id)
            ->assertSet('selectedStudentId', $student->id)
            ->assertSet('selectedPhone', '918320936486')
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
            ->call('selectConversation', '918320936486', $student->id)
            ->assertSet('selectedStudentId', $student->id)
            ->assertSee('Dear Parent, attendance update for Kapil.')
            ->assertSeeHtml('crm-wa-global-inbox__shell--chat-open')
            ->assertSeeHtml('crm-wa-global-inbox__back')
            ->assertSeeHtml('crm-wa-inbox__compose')
            ->call('clearConversation')
            ->assertSet('selectedStudentId', null)
            ->assertSet('selectedPhone', null)
            ->assertDontSeeHtml('crm-wa-global-inbox__shell--chat-open')
            ->assertStatus(200);

        Http::assertNothingSent();
    }

    public function test_reply_pending_filter_shows_only_chats_waiting_for_a_reply(): void
    {
        Http::fake();

        $admin = $this->createSuperAdmin();

        $waiting = Student::query()->create([
            'name' => 'Waiting Parent',
            'mobile' => '9811000101',
            'status' => StudentStatus::Enquiry,
        ]);

        $answered = Student::query()->create([
            'name' => 'Answered Parent',
            'mobile' => '9811000102',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.WAIT-IN',
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919811000101',
            'student_id' => $waiting->id,
            'body_preview' => 'When is the next test?',
            'message_type' => 'text',
            'status' => 'received',
            'status_at' => now(),
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.ANS-OUT',
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '919811000102',
            'student_id' => $answered->id,
            'body_preview' => 'Dear Parent, This is to inform you',
            'message_type' => 'text',
            'status' => 'sent',
            'status_at' => now()->subMinute(),
        ]);

        $this->actingAs($admin);

        Livewire::test(WhatsAppInboxPage::class)
            ->assertSet('listFilter', 'all')
            ->assertSee('Waiting Parent')
            ->assertSee('Answered Parent')
            ->assertSee('Reply pending')
            ->assertSee('When is the next test?')
            ->call('setListFilter', 'pending')
            ->assertSet('listFilter', 'pending')
            ->assertSee('Waiting Parent')
            ->assertSee('When is the next test?')
            ->assertDontSee('Answered Parent')
            ->assertDontSee('Dear Parent, This is to inform you')
            ->call('selectConversation', '919811000101', $waiting->id)
            ->assertSet('listFilter', 'pending')
            ->assertSee('Waiting Parent')
            ->assertSee('When is the next test?')
            ->assertStatus(200);
    }

    public function test_failed_filter_shows_only_chats_whose_last_school_send_failed(): void
    {
        Http::fake();

        $admin = $this->createSuperAdmin();

        $failed = Student::query()->create([
            'name' => 'Failed Parent',
            'mobile' => '9811000401',
            'status' => StudentStatus::Enquiry,
        ]);

        $delivered = Student::query()->create([
            'name' => 'Delivered Parent',
            'mobile' => '9811000402',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.FAIL-OUT',
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '919811000401',
            'student_id' => $failed->id,
            'body_preview' => 'This homework never reached WhatsApp',
            'message_type' => 'text',
            'status' => 'failed',
            'status_at' => now(),
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.OK-OUT',
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '919811000402',
            'student_id' => $delivered->id,
            'body_preview' => 'Dear Parent, homework reached this number',
            'message_type' => 'text',
            'status' => 'delivered',
            'status_at' => now()->subMinute(),
        ]);

        $this->actingAs($admin);

        Livewire::test(WhatsAppInboxPage::class)
            ->assertSet('listFilter', 'all')
            ->assertSee('Failed Parent')
            ->assertSee('Delivered Parent')
            ->assertSee('Failed')
            ->assertSee('Reply pending')
            ->call('setListFilter', 'failed')
            ->assertSet('listFilter', 'failed')
            ->assertSee('Failed Parent')
            ->assertSee('This homework never reached WhatsApp')
            ->assertDontSee('Delivered Parent')
            ->assertDontSee('Dear Parent, homework reached this number')
            ->call('selectConversation', '919811000401', $failed->id)
            ->assertSet('listFilter', 'failed')
            ->assertSeeHtml('crm-wa-global-inbox__shell--chat-open')
            ->assertSeeHtml('crm-wa-global-inbox__back')
            ->assertSee('Failed Parent')
            ->call('clearConversation')
            ->assertDontSeeHtml('crm-wa-global-inbox__shell--chat-open')
            ->assertSee('Failed Parent')
            ->assertStatus(200);
    }

    public function test_inbox_hides_page_back_and_long_hint_but_keeps_chats_and_filters(): void
    {
        Http::fake();

        Setting::setValue('site.name', 'Test Institute', 'general');
        Setting::setValue('crm.onboarding_completed', '1', 'crm');

        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        $this->get(WhatsAppInboxPage::getUrl())
            ->assertOk()
            ->assertDontSee('fi-crm-back__link', false)
            ->assertDontSee('fi-crm-back__hint', false)
            ->assertDontSee('WhatsApp inbox — all recent chats', false)
            ->assertSee('Chats', false)
            ->assertSee('Reply pending', false)
            ->assertSee('Failed', false)
            ->assertSee('crm-wa-global-inbox__list-head', false)
            ->assertSee('crm-wa-global-inbox__filters', false)
            ->assertSee('pollInbox', false)
            ->assertSee('crm-pwa-install-banner--admin', false);
    }

    public function test_poll_inbox_loads_new_inbound_without_clearing_an_empty_composer(): void
    {
        Http::fake();

        $admin = $this->createSuperAdmin();

        $student = Student::query()->create([
            'name' => 'Polling Parent',
            'mobile' => '9811000201',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.POLL-OUT',
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '919811000201',
            'student_id' => $student->id,
            'body_preview' => 'School already messaged this parent.',
            'message_type' => 'text',
            'status' => 'sent',
            'status_at' => now()->subMinutes(2),
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(WhatsAppInboxPage::class)
            ->call('selectConversation', '919811000201', $student->id)
            ->assertSee('School already messaged this parent.')
            ->assertDontSee('Can you send the timetable?')
            ->assertSet('listFilter', 'all')
            ->assertSet('metaReplyText', '');

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.POLL-IN',
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919811000201',
            'student_id' => $student->id,
            'body_preview' => 'Can you send the timetable?',
            'message_type' => 'text',
            'status' => 'received',
            'status_at' => now(),
        ]);

        $component
            ->call('pollInbox')
            ->assertSet('listFilter', 'all')
            ->assertSet('metaReplyText', '')
            ->assertSee('Can you send the timetable?')
            ->assertStatus(200);
    }

    public function test_poll_inbox_skips_refresh_while_staff_is_composing_a_reply(): void
    {
        Http::fake();

        $admin = $this->createSuperAdmin();

        $student = Student::query()->create([
            'name' => 'Draft Parent',
            'mobile' => '9811000202',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.DRAFT-IN',
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919811000202',
            'student_id' => $student->id,
            'body_preview' => 'Please call me back.',
            'message_type' => 'text',
            'status' => 'received',
            'status_at' => now()->subMinute(),
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(WhatsAppInboxPage::class)
            ->call('selectConversation', '919811000202', $student->id)
            ->set('metaReplyText', 'Draft reply still typing')
            ->assertSee('Please call me back.')
            ->assertDontSee('New inbound while typing');

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.DRAFT-IN-2',
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919811000202',
            'student_id' => $student->id,
            'body_preview' => 'New inbound while typing',
            'message_type' => 'text',
            'status' => 'received',
            'status_at' => now(),
        ]);

        $component
            ->call('pollInbox')
            ->assertSet('metaReplyText', 'Draft reply still typing')
            ->assertDontSee('New inbound while typing')
            ->assertSee('Please call me back.')
            ->call('setListFilter', 'pending')
            ->assertSet('listFilter', 'pending')
            ->assertStatus(200);
    }

    public function test_unknown_number_conversation_opens_in_inbox(): void
    {
        Http::fake();

        $admin = $this->createSuperAdmin();

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.UNKNOWN1',
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919111222333',
            'student_id' => null,
            'body_preview' => 'Hi school, I need admission info',
            'message_type' => 'text',
            'status' => 'received',
            'status_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(WhatsAppInboxPage::class)
            ->assertSee('Unknown contact')
            ->assertSee('Hi school, I need admission info')
            ->call('selectConversation', '919111222333')
            ->assertSet('selectedPhone', '919111222333')
            ->assertSet('selectedStudentId', null)
            ->assertSee('Hi school, I need admission info')
            ->assertStatus(200);
    }

    public function test_staff_number_shows_staff_name_not_unknown(): void
    {
        Http::fake();

        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token'), 'meta_whatsapp');

        $admin = $this->createSuperAdmin();

        User::factory()->create([
            'name' => 'Rohit Pal',
            'mobile' => '8109432345',
            'is_active' => true,
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.STAFFCHAT',
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '918109432345',
            'student_id' => null,
            'body_preview' => 'Hello this is me',
            'message_type' => 'text',
            'status' => 'received',
            'status_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(WhatsAppInboxPage::class)
            ->assertSee('Rohit Pal')
            ->assertSee('Staff')
            ->assertDontSee('Unknown contact')
            ->call('selectConversation', '918109432345')
            ->assertSee('Rohit Pal')
            ->assertSee('Staff')
            ->assertSet('metaSessionOpen', true)
            ->assertSee('Type a message')
            ->assertSee('wire:model="metaReplyAttachment"', false)
            ->assertStatus(200);
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
            ->call('selectConversation', '919876543210', $student->id)
            ->assertSet('selectedStudentId', $student->id)
            ->assertSet('metaSessionOpen', true)
            ->assertSee('Amit Verma')
            ->assertSee('Type a message')
            ->assertSee('Profile')
            ->assertSee('wire:model="metaReplyAttachment"', false)
            ->assertSee('24h open')
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
            ->call('selectConversation', '919876543210', $student->id)
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
            ->call('selectConversation', '919876543210', $student->id)
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

    public function test_inbox_can_select_manual_in_template_without_error(): void
    {
        Http::fake();

        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token'), 'meta_whatsapp');

        $admin = $this->createSuperAdmin();

        $student = Student::query()->create([
            'name' => 'Amit Verma',
            'mobile' => '8109462946',
            'status' => StudentStatus::Enquiry,
        ]);

        $template = \App\Models\WhatsAppTemplate::query()->create([
            'name' => 'manual_in',
            'param_count' => 4,
            'param_mappings' => [null, null, null, null],
            'body' => 'Hi {{1}}, roll {{2}}, time {{3}}, date {{4}}',
            'is_active' => true,
            'provider_meta' => [
                'body_variables' => ['1', '2', '3', '4'],
                'meta_language' => 'en',
            ],
        ]);

        \App\Models\MetaWhatsAppTemplate::query()->create([
            'name' => 'manual_in',
            'language' => 'en',
            'status' => 'APPROVED',
            'param_count' => 4,
            'body' => 'Hi {{1}}, roll {{2}}, time {{3}}, date {{4}}',
            'is_active' => true,
            'synced_at' => now(),
            'provider_meta' => ['body_variables' => ['1', '2', '3', '4']],
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.CHAT1',
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '918109462946',
            'student_id' => $student->id,
            'body_preview' => 'Hello',
            'message_type' => 'text',
            'status' => 'received',
            'status_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($admin);

        Livewire::test(WhatsAppInboxPage::class)
            ->call('selectConversation', '918109462946', $student->id)
            ->set('sendWhatsAppTemplateId', $template->id)
            ->assertSet('sendWhatsAppTemplateParamCount', 4)
            ->assertSee('Send template')
            ->assertStatus(200);
    }

    public function test_inbox_can_send_manual_in_template(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.OUT123']],
            ], 200),
        ]);

        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token'), 'meta_whatsapp');

        $admin = $this->createSuperAdmin();

        $student = Student::query()->create([
            'name' => 'Amit Verma',
            'mobile' => '8109462946',
            'status' => StudentStatus::Enquiry,
        ]);

        $template = \App\Models\WhatsAppTemplate::query()->create([
            'name' => 'manual_in',
            'param_count' => 4,
            'param_mappings' => [null, null, null, null],
            'body' => 'Hi {{1}}, roll {{2}}, time {{3}}, date {{4}}',
            'is_active' => true,
            'provider_meta' => [
                'body_variables' => ['1', '2', '3', '4'],
                'meta_language' => 'en',
            ],
        ]);

        \App\Models\MetaWhatsAppTemplate::query()->create([
            'name' => 'manual_in',
            'language' => 'en',
            'status' => 'APPROVED',
            'param_count' => 4,
            'body' => 'Hi {{1}}, roll {{2}}, time {{3}}, date {{4}}',
            'is_active' => true,
            'synced_at' => now(),
            'provider_meta' => ['body_variables' => ['1', '2', '3', '4']],
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.CHAT1',
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '918109462946',
            'student_id' => $student->id,
            'body_preview' => 'Hello',
            'message_type' => 'text',
            'status' => 'received',
            'status_at' => now()->subMinutes(5),
        ]);

        $this->actingAs($admin);

        $component = Livewire::test(WhatsAppInboxPage::class)
            ->call('selectConversation', '918109462946', $student->id)
            ->set('sendWhatsAppTemplateId', $template->id)
            ->set('sendWhatsAppTemplateParams', [
                0 => 'Amit Verma',
                1 => 'ROLL-1',
                2 => '12:06',
                3 => '07 Jul 2026',
            ])
            ->call('sendWhatsAppMessage');

        $campaign = WhatsAppCampaign::query()->latest('id')->first();

        $this->assertNotNull($campaign);
        $component->assertRedirect(WhatsAppCampaignResource::getUrl('view', ['record' => $campaign]));
    }
}
