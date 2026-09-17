<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\WhatsAppTemplate;
use App\Services\ActivityMarksWhatsAppService;
use App\Services\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityMarksWhatsAppDefaultTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_template_uses_automations_selection(): void
    {
        $attendance = WhatsAppTemplate::query()->create([
            'name' => 'parent_attendance_auto_in',
            'param_count' => 4,
            'is_active' => true,
        ]);
        $marks = WhatsAppTemplate::query()->create([
            'name' => 'test_marks',
            'param_count' => 4,
            'is_active' => true,
        ]);

        app(WhatsAppSettingsService::class)->save([
            'punch_in_autosend_live_campaign_id' => $attendance->id,
            'activity_marks_live_campaign_id' => $marks->id,
        ]);

        Setting::flushValueCache();

        $service = app(ActivityMarksWhatsAppService::class);

        $this->assertSame($marks->id, $service->defaultTemplate()?->id);
        $this->assertSame('test_marks', $service->defaultTemplateName());
        $this->assertSame($marks->id, $service->resolveTemplateId(null));
    }

    public function test_falls_back_to_test_marks_name_when_automations_not_set(): void
    {
        WhatsAppTemplate::query()->create([
            'name' => 'parent_attendance_auto_in',
            'param_count' => 4,
            'is_active' => true,
        ]);
        $marks = WhatsAppTemplate::query()->create([
            'name' => 'test_marks',
            'param_count' => 4,
            'is_active' => true,
        ]);

        $this->assertSame($marks->id, app(ActivityMarksWhatsAppService::class)->defaultTemplate()?->id);
    }

    public function test_exam_marks_guide_renders_named_placeholders(): void
    {
        $html = (string) app(WhatsAppSettingsService::class)->renderExamMarksTemplateGuide();

        $this->assertStringContainsString('test_marks', $html);
        $this->assertStringContainsString('{{name}}', $html);
        $this->assertStringContainsString('{{all_subject_marks}}', $html);
        $this->assertStringContainsString('activity.marks_summary', $html);
        $this->assertStringNotContainsString('$TestMarksWhatsAppTemplate', $html);
    }
}
