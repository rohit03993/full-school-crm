<?php

namespace Tests\Feature;

use App\Enums\StudentStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppTemplateParamResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ProcessPendingWhatsAppCampaignsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_pending_sends_queued_campaign_recipients(): void
    {
        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token-long-enough'), 'meta_whatsapp');

        config(['meta_whatsapp.graph_version' => 'v20.0']);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.TEST1']],
            ], 200),
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

        MetaWhatsAppTemplate::query()->create([
            'name' => 'test_message',
            'language' => 'en_US',
            'status' => 'APPROVED',
            'param_count' => 1,
            'body' => 'Hello {{1}}',
            'is_active' => true,
            'synced_at' => now(),
        ]);

        $campaign = WhatsAppCampaign::query()->create([
            'whatsapp_template_id' => $template->id,
            'name' => 'test_message - Kapil',
            'status' => WhatsAppCampaignStatus::Queued,
            'total_recipients' => 1,
            'campaign_variables' => ['_manual' => ['Kapil']],
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
    }

    public function test_manual_template_params_are_applied_even_without_mappings(): void
    {
        $resolver = app(WhatsAppTemplateParamResolver::class);

        $student = Student::query()->create([
            'name' => 'Kapil',
            'mobile' => '9876500001',
            'status' => StudentStatus::Enquiry,
        ]);

        $campaign = WhatsAppCampaign::query()->create([
            'whatsapp_template_id' => WhatsAppTemplate::query()->create([
                'name' => 'first_try',
                'param_count' => 0,
                'is_active' => true,
            ])->id,
            'name' => 'first_try',
            'status' => WhatsAppCampaignStatus::Queued,
            'total_recipients' => 1,
            'campaign_variables' => ['_manual' => ['Hello parent']],
        ]);

        $params = $resolver->resolveAll([], $student, null, null, $campaign);

        $this->assertSame(['Hello parent'], $params);
        $this->assertTrue($resolver->hasResolvableParams($params, $campaign));
    }
}
