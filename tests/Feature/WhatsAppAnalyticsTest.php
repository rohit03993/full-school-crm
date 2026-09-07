<?php

namespace Tests\Feature;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\RoleName;
use App\Enums\WhatsAppMessageSource;
use App\Models\MetaWhatsAppMessage;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Models\Student;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Enums\StudentStatus;
use App\Filament\Pages\WhatsAppAnalyticsPage;
use App\Services\MetaWhatsAppCostEstimator;
use App\Services\MetaWhatsAppPricingAnalyticsService;
use App\Services\WhatsAppAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class WhatsAppAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    public function test_cost_estimator_uses_template_category_from_meta_sync(): void
    {
        MetaWhatsAppTemplate::query()->create([
            'name' => 'manual_in',
            'language' => 'en',
            'status' => 'APPROVED',
            'param_count' => 1,
            'body' => 'Hi {{1}}',
            'is_active' => true,
            'provider_meta' => ['category' => 'UTILITY', 'body_variables' => ['1']],
            'synced_at' => now(),
        ]);

        $estimate = app(MetaWhatsAppCostEstimator::class)->estimateForTemplate('manual_in', 'en');

        $this->assertSame('UTILITY', $estimate['category']);
        $this->assertGreaterThan(0, $estimate['cost_inr']);
    }

    public function test_meta_pricing_analytics_parses_official_cost(): void
    {
        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.waba_id', '999888777', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token'), 'meta_whatsapp');

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'pricing_analytics' => [
                    'data' => [[
                        'data_points' => [
                            [
                                'start' => 1749106800,
                                'end' => 1749193200,
                                'country' => 'IN',
                                'pricing_type' => 'REGULAR',
                                'pricing_category' => 'MARKETING',
                                'volume' => 10,
                                'cost' => 7.846,
                            ],
                            [
                                'start' => 1749106800,
                                'end' => 1749193200,
                                'country' => 'IN',
                                'pricing_type' => 'REGULAR',
                                'pricing_category' => 'UTILITY',
                                'volume' => 5,
                                'cost' => 1.75,
                            ],
                        ],
                    ]],
                ],
                'id' => '999888777',
            ], 200),
        ]);

        $result = app(MetaWhatsAppPricingAnalyticsService::class)->fetchForRange(
            Carbon::parse('2025-06-01'),
            Carbon::parse('2025-06-30'),
        );

        $this->assertSame('success', $result['status']);
        $this->assertSame(9.596, $result['total_cost']);
        $this->assertSame(15, $result['total_volume']);
        $this->assertSame(7.846, $result['by_category']['MARKETING']['cost']);
    }

    public function test_analytics_summary_includes_campaign_costs(): void
    {
        $student = Student::query()->create([
            'name' => 'Kapil',
            'mobile' => '8320936488',
            'status' => StudentStatus::Enquiry,
        ]);

        $template = WhatsAppTemplate::query()->create([
            'name' => 'alert_batch',
            'param_count' => 0,
            'body' => 'Hello',
            'is_active' => true,
        ]);

        $campaign = WhatsAppCampaign::query()->create([
            'whatsapp_template_id' => $template->id,
            'name' => 'Batch alert',
            'status' => 'completed',
            'total_recipients' => 1,
            'sent_count' => 1,
            'failed_count' => 0,
            'estimated_total_cost_inr' => 0.7846,
            'shot_at' => now(),
        ]);

        $message = MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.OUT1',
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '918320936488',
            'student_id' => $student->id,
            'body_preview' => 'Hello',
            'message_type' => 'text',
            'conversation_category' => 'MARKETING',
            'message_source' => WhatsAppMessageSource::Campaign->value,
            'estimated_cost_inr' => 0.7846,
            'status' => 'sent',
            'status_at' => now(),
        ]);

        WhatsAppCampaignRecipient::query()->create([
            'whatsapp_campaign_id' => $campaign->id,
            'student_id' => $student->id,
            'phone' => '918320936488',
            'wamid' => 'wamid.OUT1',
            'meta_whatsapp_message_id' => $message->id,
            'status' => 'sent',
            'estimated_cost_inr' => 0.7846,
        ]);

        $summary = app(WhatsAppAnalyticsService::class)->summary(now()->subDay(), now()->addDay());

        $this->assertSame(1, $summary['local']['total_messages']);
        $this->assertSame(0.7846, $summary['local']['total_cost_inr']);
        $this->assertCount(1, $summary['campaigns']);
        $this->assertSame(0.7846, $summary['campaigns'][0]['estimated_total_cost_inr']);
        $this->assertArrayHasKey('gap', $summary);
        $this->assertSame(1, $summary['gap']['crm_volume']);
        $this->assertArrayHasKey('by_staff', $summary);
    }

    public function test_staff_usage_breakdown_groups_by_sender(): void
    {
        $staff = User::factory()->create(['name' => 'Anita Desk', 'is_active' => true]);
        $other = User::factory()->create(['name' => 'Ravi Bulk', 'is_active' => true]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.STAFF1',
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '918320936488',
            'sent_by_user_id' => $staff->id,
            'send_actor' => 'staff',
            'body_preview' => 'Inbox reply',
            'message_type' => 'text',
            'conversation_category' => 'SERVICE',
            'message_source' => WhatsAppMessageSource::Inbox->value,
            'estimated_cost_inr' => 0.15,
            'status' => 'sent',
            'status_at' => now(),
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.STAFF2',
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '918320936489',
            'sent_by_user_id' => $other->id,
            'send_actor' => 'staff',
            'body_preview' => 'Campaign',
            'message_type' => 'text',
            'conversation_category' => 'UTILITY',
            'message_source' => WhatsAppMessageSource::Campaign->value,
            'estimated_cost_inr' => 0.5,
            'status' => 'sent',
            'status_at' => now(),
        ]);

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.AUTO1',
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '918320936490',
            'send_actor' => 'automatic',
            'body_preview' => 'Punch alert',
            'message_type' => 'text',
            'conversation_category' => 'UTILITY',
            'message_source' => WhatsAppMessageSource::Punch->value,
            'estimated_cost_inr' => 0.4,
            'status' => 'sent',
            'status_at' => now(),
        ]);

        $byStaff = app(WhatsAppAnalyticsService::class)->breakdownByStaff(now()->subDay(), now()->addDay());

        $this->assertCount(2, $byStaff);
        $this->assertSame('Ravi Bulk', $byStaff[0]['name']);
        $this->assertSame(0.5, $byStaff[0]['cost_inr']);
        $this->assertSame('Anita Desk', $byStaff[1]['name']);
        $this->assertSame(0.15, $byStaff[1]['cost_inr']);
    }

    public function test_usage_and_cost_page_renders(): void
    {
        Http::fake([
            'https://graph.facebook.com/*' => Http::response(['pricing_analytics' => ['data' => []]], 200),
        ]);

        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $this->actingAs($admin);

        Livewire::test(WhatsAppAnalyticsPage::class)
            ->assertSuccessful()
            ->assertSee('Cost by source')
            ->assertSee('Staff usage');
    }

    public function test_coverage_gap_compares_meta_and_crm_volumes(): void
    {
        $service = app(WhatsAppAnalyticsService::class);

        $gap = $service->coverageGap(
            [
                'status' => 'success',
                'total_volume' => 100,
                'total_cost' => 11.5,
                'currency' => 'INR',
            ],
            [
                'total_messages' => 20,
                'total_cost_inr' => 2.3,
            ],
        );

        $this->assertTrue($gap['meta_available']);
        $this->assertSame(100, $gap['meta_volume']);
        $this->assertSame(20, $gap['crm_volume']);
        $this->assertSame(80, $gap['missing_from_crm']);
        $this->assertSame(20.0, $gap['coverage_percent']);
        $this->assertSame(11.5, $gap['meta_cost']);
        $this->assertSame(2.3, $gap['crm_estimated_cost']);
    }
}
