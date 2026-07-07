<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use App\Services\Punch\PunchWhatsAppService;
use App\Services\WhatsAppTemplateParamResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PunchWhatsAppServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-test-token'), 'meta_whatsapp');
        Setting::flushValueCache();

        config(['meta_whatsapp.graph_version' => 'v20.0']);

        Http::fake([
            'https://graph.facebook.com/*' => Http::response([
                'messages' => [['id' => 'wamid.PUNCH']],
            ], 200),
        ]);
    }

    public function test_biometric_in_uses_machine_template(): void
    {
        $this->travelTo('2026-06-20 09:00:00');

        [$student, $machineTemplate, $manualTemplate] = $this->seedTemplatesAndStudent();

        Setting::setValue('whatsapp.punch_autosend_enabled', '1', 'whatsapp');
        Setting::setValue('whatsapp.punch_in_autosend_template_id', (string) $machineTemplate->id, 'whatsapp');
        Setting::setValue('whatsapp.punch_manual_in_autosend_template_id', (string) $manualTemplate->id, 'whatsapp');
        Setting::flushValueCache();

        $sent = app(PunchWhatsAppService::class)->maybeSendForPunch(
            $student,
            'ROLL-WA-1',
            '2026-06-20',
            '09:00:00',
            'IN',
            null,
        );

        $this->assertTrue($sent);
        $campaign = WhatsAppCampaign::query()->first();
        $this->assertNotNull($campaign);
        $this->assertSame($machineTemplate->id, $campaign->whatsapp_template_id);
        $this->assertSame('punch_biometric', $campaign->campaignVariable('audience_source'));
    }

    public function test_manual_in_uses_manual_template_when_set(): void
    {
        $this->travelTo('2026-06-20 09:05:00');

        [$student, $machineTemplate, $manualTemplate] = $this->seedTemplatesAndStudent();
        $staff = User::factory()->create(['is_active' => true]);

        Setting::setValue('whatsapp.punch_autosend_enabled', '1', 'whatsapp');
        Setting::setValue('whatsapp.punch_in_autosend_template_id', (string) $machineTemplate->id, 'whatsapp');
        Setting::setValue('whatsapp.punch_manual_in_autosend_template_id', (string) $manualTemplate->id, 'whatsapp');
        Setting::flushValueCache();

        $sent = app(PunchWhatsAppService::class)->maybeSendForPunch(
            $student,
            'ROLL-WA-1',
            '2026-06-20',
            '09:05:00',
            'IN',
            $staff,
        );

        $this->assertTrue($sent);
        $campaign = WhatsAppCampaign::query()->first();
        $this->assertNotNull($campaign);
        $this->assertSame($manualTemplate->id, $campaign->whatsapp_template_id);
        $this->assertSame('punch_manual', $campaign->campaignVariable('audience_source'));
        $this->assertSame(1, $campaign->total_recipients);
        $this->assertSame(1, $campaign->recipients()->count());
    }

    public function test_manual_in_falls_back_to_biometric_template_when_manual_blank(): void
    {
        $this->travelTo('2026-06-20 09:10:00');

        [$student, $machineTemplate] = $this->seedTemplatesAndStudent();
        $staff = User::factory()->create(['is_active' => true]);

        Setting::setValue('whatsapp.punch_autosend_enabled', '1', 'whatsapp');
        Setting::setValue('whatsapp.punch_in_autosend_template_id', (string) $machineTemplate->id, 'whatsapp');
        Setting::flushValueCache();

        app(PunchWhatsAppService::class)->maybeSendForPunch(
            $student,
            'ROLL-WA-1',
            '2026-06-20',
            '09:10:00',
            'IN',
            $staff,
        );

        $this->assertSame($machineTemplate->id, WhatsAppCampaign::query()->first()?->whatsapp_template_id);
    }

    public function test_manual_in_resolves_positional_template_params_from_punch_data(): void
    {
        $this->travelTo('2026-07-07 08:04:00');

        [$student, , $manualTemplate] = $this->seedTemplatesAndStudent();
        $staff = User::factory()->create(['is_active' => true]);

        $manualTemplate->update([
            'name' => 'manual_in',
            'param_count' => 4,
            'param_mappings' => [null, null, null, null],
            'body' => 'Hi {{1}}, roll {{2}}, time {{3}}, date {{4}}',
            'provider_meta' => [
                'body_variables' => ['1', '2', '3', '4'],
                'meta_language' => 'en',
            ],
        ]);

        MetaWhatsAppTemplate::query()->where('name', 'manual_in_tpl')->update([
            'name' => 'manual_in',
            'param_count' => 4,
            'param_mappings' => [null, null, null, null],
            'body' => 'Hi {{1}}, roll {{2}}, time {{3}}, date {{4}}',
            'provider_meta' => [
                'body_variables' => ['1', '2', '3', '4'],
            ],
        ]);

        Setting::setValue('whatsapp.punch_autosend_enabled', '1', 'whatsapp');
        Setting::setValue('whatsapp.punch_manual_in_autosend_template_id', (string) $manualTemplate->id, 'whatsapp');
        Setting::flushValueCache();

        $outcome = app(PunchWhatsAppService::class)->outcomeForPunch(
            $student,
            'FOL-2028-000005',
            '2026-07-07',
            '08:04:00',
            'IN',
            $staff,
        );

        $this->assertTrue($outcome['queued']);
        $this->assertSame('Parent WhatsApp sent.', $outcome['message']);

        $campaign = WhatsAppCampaign::query()->firstOrFail();
        $params = app(WhatsAppTemplateParamResolver::class)->resolveAll(
            $manualTemplate->fresh()->paramSources(),
            $student->fresh(),
            $staff,
            null,
            $campaign,
        );

        $this->assertSame($student->name, $params[0]);
        $this->assertSame('FOL-2028-000005', $params[1]);
        $this->assertSame('08:04:00', $params[2]);
        $this->assertSame('2026-07-07', $params[3]);
    }

    /**
     * @return array{0: Student, 1: WhatsAppTemplate, 2: WhatsAppTemplate}
     */
    private function seedTemplatesAndStudent(): array
    {
        $machine = WhatsAppTemplate::query()->create([
            'name' => 'biometric_in_tpl',
            'param_count' => 1,
            'param_mappings' => ['student.name'],
            'body' => 'Machine {{1}}',
            'is_active' => true,
        ]);

        $manual = WhatsAppTemplate::query()->create([
            'name' => 'manual_in_tpl',
            'param_count' => 1,
            'param_mappings' => ['student.name'],
            'body' => 'Manual {{1}}',
            'is_active' => true,
        ]);

        $this->syncMetaTemplate($machine);
        $this->syncMetaTemplate($manual);

        $student = Student::query()->create([
            'name' => 'WhatsApp Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876543210',
            'status' => StudentStatus::Enrolled,
        ]);

        // Biometric punches pass null staff; service still needs an active user to queue the campaign.
        User::factory()->create(['is_active' => true]);

        return [$student, $machine, $manual];
    }

    private function syncMetaTemplate(WhatsAppTemplate $template): void
    {
        MetaWhatsAppTemplate::query()->create([
            'name' => $template->name,
            'language' => 'en',
            'status' => 'APPROVED',
            'param_count' => $template->param_count,
            'param_mappings' => $template->param_mappings,
            'body' => $template->body,
            'provider_meta' => $template->provider_meta,
            'is_active' => true,
            'synced_at' => now(),
        ]);
    }
}
