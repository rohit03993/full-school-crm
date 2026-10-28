<?php

namespace Tests\Feature;

use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Enums\RoleName;
use App\Filament\Pages\WhatsAppHubPage;
use App\Models\MetaWhatsAppMessage;
use App\Models\Setting;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\MetaWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppAccountHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_hub_shows_meta_quality_and_people_limit(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);
        app(LicenseService::class)->save([
            'plan' => LicensePlan::Custom->value,
            'features' => [LicenseFeature::WhatsApp->value],
            'expires_at' => now()->addYear()->toDateString(),
        ]);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);
        $this->actingAs($admin);

        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token'), 'meta_whatsapp');
        config(['meta_whatsapp.graph_version' => 'v20.0']);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.A1',
            'direction' => 'outbound',
            'phone' => '919811111111',
            'template_name' => 'student_birthday',
            'status' => 'sent',
            'status_at' => now(),
        ]);
        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.A2',
            'direction' => 'outbound',
            'phone' => '919811111111',
            'template_name' => 'student_birthday',
            'status' => 'delivered',
            'status_at' => now(),
        ]);
        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.B1',
            'direction' => 'outbound',
            'phone' => '919822222222',
            'template_name' => 'fee_notice',
            'status' => 'sent',
            'status_at' => now(),
        ]);
        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.C1',
            'direction' => 'outbound',
            'phone' => '919833333333',
            'template_name' => 'student_birthday',
            'status' => 'failed',
            'status_at' => now(),
        ]);

        Http::fake([
            'https://graph.facebook.com/v20.0/1234567890*' => Http::response([
                'display_phone_number' => '+91 98765 43210',
                'verified_name' => 'Horizon School',
                'quality_rating' => 'GREEN',
                'status' => 'CONNECTED',
                'whatsapp_business_manager_messaging_limit' => 'TIER_2K',
                'id' => '1234567890',
            ]),
        ]);

        Livewire::test(WhatsAppHubPage::class)
            ->assertSee('Connected')
            ->assertSee('Horizon School')
            ->assertSee('Good')
            ->assertSee('2,000 people')
            ->assertSee('2');

        Http::assertSentCount(1);
    }

    public function test_account_health_says_when_whatsapp_is_not_connected(): void
    {
        $health = app(MetaWhatsAppService::class)->accountHealth();

        $this->assertFalse($health['ok']);
        $this->assertStringContainsString('not connected', strtolower($health['message']));
    }
}
