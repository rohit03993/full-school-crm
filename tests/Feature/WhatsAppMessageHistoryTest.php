<?php

namespace Tests\Feature;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\RoleName;
use App\Filament\Pages\ListMetaWhatsAppMessagesPage;
use App\Models\MetaWhatsAppMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppMessageHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_message_history_filters_by_category_and_shows_the_charge(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.UTIL',
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '919999000001',
            'template_name' => 'parent_attendance_auto_in',
            'body_preview' => 'Attendance utility',
            'conversation_category' => 'UTILITY',
            'estimated_cost_inr' => 0.115,
            'status' => 'delivered',
            'status_at' => now(),
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.MKT',
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '919999000002',
            'template_name' => 'student_birthday',
            'body_preview' => 'Birthday marketing',
            'conversation_category' => 'MARKETING',
            'estimated_cost_inr' => 0.8631,
            'status' => 'failed',
            'status_at' => now(),
        ]);

        $this->actingAs($admin);

        Livewire::test(ListMetaWhatsAppMessagesPage::class)
            ->assertSee('Utility')
            ->assertSee('Marketing')
            ->assertSee('₹0.115')
            ->assertSee('₹0')
            ->set('categoryFilter', 'MARKETING')
            ->assertSee('student_birthday')
            ->assertDontSee('parent_attendance_auto_in')
            ->set('categoryFilter', '')
            ->set('chargeFilter', 'charged')
            ->assertSee('parent_attendance_auto_in')
            ->assertDontSee('student_birthday');
    }
}
