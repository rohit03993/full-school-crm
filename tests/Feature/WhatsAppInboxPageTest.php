<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Pages\WhatsAppInboxPage;
use App\Models\MetaWhatsAppMessage;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    protected function createSuperAdmin(): User
    {
        $role = Role::findOrCreate(RoleName::SuperAdmin->value);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
