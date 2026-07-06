<?php

namespace Tests\Feature;

use App\Enums\StudentStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessPendingWhatsAppCampaignsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_pending_sends_queued_campaign_recipients(): void
    {
        config([
            'services.pal_digital.api_key' => 'wsk.550e8400-e29b-41d4-a716-446655440000.secretpart',
            'services.pal_digital.api_url' => 'https://wa.paldigital.in/api/v1/campaign/t1/api/v2',
        ]);
        Setting::setValue('meta_whatsapp.enabled', '0', 'meta_whatsapp');

        Http::fake([
            'https://wa.paldigital.in/*' => Http::response(['status' => 'success'], 200),
        ]);

        $admin = User::factory()->create(['is_active' => true]);
        $student = Student::query()->create([
            'name' => 'Kapil',
            'mobile' => '9876500001',
            'status' => StudentStatus::Enquiry,
        ]);
        $template = WhatsAppTemplate::query()->create([
            'name' => 'test_message',
            'param_count' => 1,
            'param_mappings' => ['student.name'],
            'is_active' => true,
        ]);

        $campaign = WhatsAppCampaign::query()->create([
            'whatsapp_template_id' => $template->id,
            'name' => 'test_message - Kapil',
            'status' => WhatsAppCampaignStatus::Queued,
            'total_recipients' => 1,
            'created_by' => $admin->id,
            'shot_by' => $admin->id,
            'shot_at' => now(),
            'started_at' => now(),
        ]);

        WhatsAppCampaignRecipient::query()->create([
            'whatsapp_campaign_id' => $campaign->id,
            'student_id' => $student->id,
            'phone' => (string) $student->mobile,
            'status' => WhatsAppRecipientStatus::Pending,
        ]);

        $exitCode = Artisan::call('whatsapp:process-pending');

        $this->assertSame(0, $exitCode);
        $this->assertDatabaseHas('whatsapp_campaign_recipients', [
            'whatsapp_campaign_id' => $campaign->id,
            'status' => WhatsAppRecipientStatus::Sent->value,
        ]);
        $this->assertDatabaseHas('whatsapp_campaigns', [
            'id' => $campaign->id,
            'status' => WhatsAppCampaignStatus::Completed->value,
        ]);
    }
}
