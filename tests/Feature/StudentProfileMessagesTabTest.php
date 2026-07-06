<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Pages\StudentProfilePage;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
            'waTemplates' => collect(),
            'waTemplateSyncHint' => null,
            'sendWhatsAppTemplateId' => null,
            'sendWhatsAppTemplateFields' => [],
            'sendWhatsAppTemplateParamCount' => 0,
            'sendWhatsAppTemplatePreview' => null,
            'sendWhatsAppSelectedTemplateName' => null,
        ])->render();

        $this->assertStringContainsString('Pick a template, fill only the fields it needs', $html);
    }

    protected function createSuperAdmin(): User
    {
        $role = Role::findOrCreate(RoleName::SuperAdmin->value);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
