<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\WhatsAppSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppSendingChannelsSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_lists_attendance_fees_and_homework_separately(): void
    {
        Setting::setValue('whatsapp.punch_autosend_enabled', '0', 'whatsapp');
        Setting::setValue('whatsapp.fee_reminder_autosend_enabled', '1', 'whatsapp');
        Setting::setValue('whatsapp.homework_not_done_autosend_enabled', '1', 'whatsapp');

        $channels = collect(app(WhatsAppSettingsService::class)->sendingChannelStatuses())
            ->keyBy('key');

        $this->assertFalse($channels['punch']['enabled']);
        $this->assertTrue($channels['fees']['enabled']);
        $this->assertTrue($channels['homework']['enabled']);

        $html = (string) app(WhatsAppSettingsService::class)->renderSendingChannelsSummary();

        $this->assertStringContainsString('Student attendance (to parents)', $html);
        $this->assertStringContainsString('Fee reminders', $html);
        $this->assertStringContainsString('Homework not done', $html);
        $this->assertStringContainsString('Open Automations', $html);
    }
}
