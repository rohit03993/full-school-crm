<?php

namespace Tests\Feature;

use App\Enums\WhatsAppLiveCampaignStatus;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Models\WhatsAppLiveCampaign;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppAutomationTemplatePointerTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_prefixed_template_id_and_legacy_live_campaign_id(): void
    {
        $template = WhatsAppTemplate::query()->create([
            'name' => 'fee_reminder_upcoming',
            'param_count' => 4,
            'param_mappings' => ['institute.name', 'student.name', 'fee.pending_amount', 'fee.due_date'],
            'is_active' => true,
        ]);

        $meta = MetaWhatsAppTemplate::query()->create([
            'name' => 'fee_reminder_upcoming',
            'language' => 'en',
            'status' => 'APPROVED',
            'param_count' => 4,
            'is_active' => true,
            'synced_at' => now(),
        ]);

        $live = WhatsAppLiveCampaign::query()->create([
            'name' => 'fee_upcoming_live',
            'meta_whatsapp_template_id' => $meta->id,
            'status' => WhatsAppLiveCampaignStatus::Live,
            'went_live_at' => now(),
        ]);

        $settings = app(WhatsAppSettingsService::class);

        $fromPrefix = $settings->resolveAutomationTemplate(
            WhatsAppSettingsService::AUTOMATION_TEMPLATE_PREFIX.$template->id,
        );
        $fromLive = $settings->resolveAutomationTemplate((string) $live->id);

        $this->assertNotNull($fromPrefix);
        $this->assertSame($template->id, $fromPrefix->id);
        $this->assertNotNull($fromLive);
        $this->assertSame($template->id, $fromLive->id);
        $this->assertSame((string) $template->id, $settings->formTemplateIdFromStored((string) $live->id));
    }

    public function test_save_stores_template_prefix_not_raw_id(): void
    {
        $template = WhatsAppTemplate::query()->create([
            'name' => 'homework_not_done',
            'param_count' => 5,
            'is_active' => true,
        ]);

        $settings = app(WhatsAppSettingsService::class);
        $settings->save([
            'homework_not_done_autosend_enabled' => true,
            'homework_not_done_live_campaign_id' => $template->id,
        ]);

        Setting::flushValueCache();

        $stored = Setting::getValue('whatsapp.homework_not_done_live_campaign_id');

        $this->assertSame('template:'.$template->id, $stored);
        $this->assertSame($template->id, $settings->resolveAutomationTemplate($stored)?->id);
    }

    public function test_save_stores_homework_share_template_prefix(): void
    {
        $combined = WhatsAppTemplate::query()->create([
            'name' => 'homework_combined',
            'param_count' => 4,
            'is_active' => true,
        ]);

        $share = WhatsAppTemplate::query()->create([
            'name' => 'homework_api',
            'param_count' => 4,
            'is_active' => true,
        ]);

        $settings = app(WhatsAppSettingsService::class);
        $settings->save([
            'homework_combined_live_campaign_id' => $combined->id,
            'homework_share_live_campaign_id' => $share->id,
        ]);

        Setting::flushValueCache();

        $this->assertSame('template:'.$combined->id, Setting::getValue('whatsapp.homework_combined_live_campaign_id'));
        $this->assertSame('template:'.$share->id, Setting::getValue('whatsapp.homework_share_live_campaign_id'));
        $this->assertSame('homework_combined', Setting::getValue('whatsapp.homework_combined_template_name'));
        $this->assertSame('homework_api', Setting::getValue('whatsapp.homework_template_name'));
    }

    public function test_fee_reminder_guide_renders_without_undefined_class_variable(): void
    {
        $html = (string) app(WhatsAppSettingsService::class)->renderFeeReminderTemplateGuide();

        $this->assertStringContainsString('fee_reminder', $html);
        $this->assertStringContainsString('also works', $html);
        $this->assertStringNotContainsString('$FeeReminderWhatsAppTemplate', $html);
    }
}
