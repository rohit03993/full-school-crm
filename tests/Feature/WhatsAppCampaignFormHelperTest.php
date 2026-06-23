<?php

namespace Tests\Feature;

use App\Models\WhatsAppTemplate;
use App\Support\WhatsAppCampaignFormHelper;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppCampaignFormHelperTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_daily_sequential_campaign_name(): void
    {
        $name = WhatsAppCampaignFormHelper::generateDefaultName();

        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}-\d{3}$/', $name);
        $this->assertStringEndsWith('-001', $name);
    }

    public function test_message_detail_fields_match_template_param_mappings(): void
    {
        $template = WhatsAppTemplate::query()->create([
            'name' => 'hello_world',
            'param_count' => 6,
            'param_mappings' => [
                'student.name',
                'batch.name',
                'campaign.topic',
                'campaign.subject',
                'campaign.date',
                'campaign.time',
            ],
        ]);

        $fields = WhatsAppCampaignFormHelper::messageDetailFields($template->id);

        $this->assertCount(5, $fields);
        $this->assertInstanceOf(Placeholder::class, $fields[0]);
        $this->assertInstanceOf(TextInput::class, $fields[1]);
    }

    public function test_template_preview_card_includes_body_and_auto_badges(): void
    {
        $template = WhatsAppTemplate::query()->create([
            'name' => 'attendance',
            'param_count' => 2,
            'param_mappings' => ['student.name', 'campaign.date'],
            'body' => 'Hello {{1}} on {{2}}',
            'provider_meta' => ['body_variables' => ['student_name', 'date']],
        ]);

        $html = WhatsAppCampaignFormHelper::renderTemplatePreviewCard($template->id)->toHtml();

        $this->assertStringContainsString('Hello {{1}} on {{2}}', $html);
        $this->assertStringContainsString('Auto', $html);
        $this->assertStringContainsString('student name', $html);
    }

    public function test_default_campaign_variables_prefills_date_and_time(): void
    {
        $template = WhatsAppTemplate::query()->create([
            'name' => 'attendance',
            'param_count' => 4,
            'param_mappings' => [
                'student.name',
                'student.enrollment_number',
                'campaign.time',
                'campaign.date',
            ],
        ]);

        $variables = WhatsAppCampaignFormHelper::defaultCampaignVariables($template->id);

        $this->assertArrayHasKey('date', $variables);
        $this->assertArrayHasKey('time', $variables);
        $this->assertSame(now()->toDateString(), $variables['date']);
    }
}
