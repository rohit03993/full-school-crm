<?php

namespace App\Services;

use App\Filament\Pages\AttendancePage;
use App\Filament\Pages\ManageAttendanceBiometricPage;
use App\Models\Setting;
use App\Models\WhatsAppTemplate;
use App\Support\CrmNavigation;
use Illuminate\Support\HtmlString;

class WhatsAppSettingsService
{
    /**
     * @return array<string, mixed>
     */
    public function getFormData(): array
    {
        return [
            'postcall_autosend_enabled' => (bool) Setting::getValue('whatsapp.postcall_autosend_enabled', false),
            'postcall_autosend_live_campaign_id' => Setting::getValue('whatsapp.postcall_autosend_live_campaign_id'),
            'fee_reminder_autosend_enabled' => (bool) Setting::getValue('whatsapp.fee_reminder_autosend_enabled', false),
            'fee_reminder_live_campaign_id' => Setting::getValue('whatsapp.fee_reminder_live_campaign_id'),
            'attendance_autosend_enabled' => (bool) Setting::getValue('whatsapp.attendance_autosend_enabled', false),
            'attendance_autosend_live_campaign_id' => Setting::getValue('whatsapp.attendance_autosend_live_campaign_id'),
            'punch_autosend_enabled' => (bool) Setting::getValue('whatsapp.punch_autosend_enabled', true),
            'punch_in_autosend_live_campaign_id' => Setting::getValue('whatsapp.punch_in_autosend_live_campaign_id'),
            'punch_out_autosend_live_campaign_id' => Setting::getValue('whatsapp.punch_out_autosend_live_campaign_id'),
            'punch_manual_in_autosend_live_campaign_id' => Setting::getValue('whatsapp.punch_manual_in_autosend_live_campaign_id'),
            'punch_manual_out_autosend_live_campaign_id' => Setting::getValue('whatsapp.punch_manual_out_autosend_live_campaign_id'),
            'campaign_batch_size' => (int) Setting::getValue('whatsapp.campaign_batch_size', config('whatsapp.batch_size', 10)),
            'campaign_batch_delay_seconds' => (int) Setting::getValue(
                'whatsapp.campaign_next_batch_delay_seconds',
                config('whatsapp.next_batch_delay_seconds', 2),
            ),
        ];
    }

    public function renderActiveProviderNotice(): HtmlString
    {
        if (! app(WhatsAppProviderResolver::class)->isMetaActive()) {
            return new HtmlString('');
        }

        return new HtmlString(
            '<div class="rounded-lg border border-info-200 bg-info-50 p-3 dark:border-info-500/30 dark:bg-info-500/10">'
            .'<p class="text-sm font-medium text-info-800 dark:text-info-200">WhatsApp routing is active for this institute</p>'
            .'<p class="mt-1 text-sm text-info-700 dark:text-info-300">Campaigns and automations send through Meta Cloud API from this CRM.</p>'
            .'</div>'
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool}
     */
    public function save(array $data): array
    {
        Setting::setValue(
            'whatsapp.postcall_autosend_enabled',
            ! empty($data['postcall_autosend_enabled']) ? '1' : '0',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.postcall_autosend_live_campaign_id',
            filled($data['postcall_autosend_live_campaign_id'] ?? null) ? (string) $data['postcall_autosend_live_campaign_id'] : '',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.fee_reminder_autosend_enabled',
            ! empty($data['fee_reminder_autosend_enabled']) ? '1' : '0',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.fee_reminder_live_campaign_id',
            filled($data['fee_reminder_live_campaign_id'] ?? null) ? (string) $data['fee_reminder_live_campaign_id'] : '',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.attendance_autosend_enabled',
            ! empty($data['attendance_autosend_enabled']) ? '1' : '0',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.attendance_autosend_live_campaign_id',
            filled($data['attendance_autosend_live_campaign_id'] ?? null) ? (string) $data['attendance_autosend_live_campaign_id'] : '',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.punch_autosend_enabled',
            ! empty($data['punch_autosend_enabled']) ? '1' : '0',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.punch_in_autosend_live_campaign_id',
            filled($data['punch_in_autosend_live_campaign_id'] ?? null) ? (string) $data['punch_in_autosend_live_campaign_id'] : '',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.punch_out_autosend_live_campaign_id',
            filled($data['punch_out_autosend_live_campaign_id'] ?? null) ? (string) $data['punch_out_autosend_live_campaign_id'] : '',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.punch_manual_in_autosend_live_campaign_id',
            filled($data['punch_manual_in_autosend_live_campaign_id'] ?? null) ? (string) $data['punch_manual_in_autosend_live_campaign_id'] : '',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.punch_manual_out_autosend_live_campaign_id',
            filled($data['punch_manual_out_autosend_live_campaign_id'] ?? null) ? (string) $data['punch_manual_out_autosend_live_campaign_id'] : '',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.campaign_batch_size',
            (string) max(1, min(50, (int) ($data['campaign_batch_size'] ?? 10))),
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.campaign_next_batch_delay_seconds',
            (string) max(0, min(60, (int) ($data['campaign_batch_delay_seconds'] ?? 2))),
            'whatsapp',
        );

        return ['ok' => true];
    }

    /**
     * @return array<int, string>
     */
    public function liveCampaignOptions(): array
    {
        return \App\Models\WhatsAppLiveCampaign::query()
            ->with('metaTemplate')
            ->where('status', \App\Enums\WhatsAppLiveCampaignStatus::Live)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (\App\Models\WhatsAppLiveCampaign $campaign): array => [
                $campaign->id => $campaign->name.' → '.($campaign->metaTemplate?->name ?? 'template'),
            ])
            ->all();
    }

    public function resolveAutomationTemplate(?string $liveCampaignId, ?string $legacyTemplateId = null): ?WhatsAppTemplate
    {
        $templateId = app(WhatsAppLiveCampaignService::class)->whatsAppTemplateIdForCampaign(
            filled($liveCampaignId) ? (int) $liveCampaignId : null,
        );

        if ($templateId) {
            return WhatsAppTemplate::query()->whereKey($templateId)->where('is_active', true)->first();
        }

        if (filled($legacyTemplateId)) {
            return WhatsAppTemplate::query()->whereKey($legacyTemplateId)->where('is_active', true)->first();
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function templateOptions(): array
    {
        return WhatsAppTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function templateName(?string $id): ?string
    {
        if (! filled($id)) {
            return null;
        }

        return WhatsAppTemplate::query()->whereKey($id)->value('name');
    }

    public function renderAttendanceAutomationGuide(): HtmlString
    {
        $biometricUrl = e(ManageAttendanceBiometricPage::getUrl());
        $attendanceUrl = e(AttendancePage::getUrl());

        return new HtmlString(
            '<div class="overflow-hidden rounded-xl border border-primary-200/60 bg-primary-50/40 dark:border-primary-500/20 dark:bg-primary-500/5">'
            .'<div class="border-b border-primary-200/60 px-4 py-3 dark:border-primary-500/20">'
            .'<p class="text-sm font-bold text-gray-950 dark:text-white">Which action sends which message?</p>'
            .'<p class="mt-1 text-xs text-gray-600 dark:text-gray-300">Turn each option on below and pick a synced template. '
            .'<a href="'.$biometricUrl.'" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Biometric setup</a> · '
            .'<a href="'.$attendanceUrl.'" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Attendance screen</a>'
            .'</p></div>'
            .'<div class="overflow-x-auto"><table class="w-full min-w-[36rem] text-left text-sm">'
            .'<thead class="bg-white/60 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-black/20 dark:text-gray-400">'
            .'<tr><th class="px-4 py-2">Action</th><th class="px-4 py-2">Trigger</th><th class="px-4 py-2">Template to pick below</th></tr></thead><tbody class="divide-y divide-primary-100 dark:divide-primary-500/10">'
            .'<tr class="bg-white/40 dark:bg-transparent"><td class="px-4 py-3 font-semibold text-emerald-700 dark:text-emerald-300">Machine check-in (IN)</td>'
            .'<td class="px-4 py-3 text-gray-600 dark:text-gray-300">EasyTimePro → <code class="text-xs">punch_logs</code></td>'
            .'<td class="px-4 py-3 text-gray-600 dark:text-gray-300"><strong>Biometric IN</strong></td></tr>'
            .'<tr><td class="px-4 py-3 font-semibold text-rose-700 dark:text-rose-300">Machine check-out (OUT)</td>'
            .'<td class="px-4 py-3 text-gray-600 dark:text-gray-300">Device punch OUT from punch_logs</td>'
            .'<td class="px-4 py-3 text-gray-600 dark:text-gray-300"><strong>Biometric OUT</strong></td></tr>'
            .'<tr class="bg-white/40 dark:bg-transparent"><td class="px-4 py-3 font-semibold text-emerald-700 dark:text-emerald-300">Manual check-in (IN)</td>'
            .'<td class="px-4 py-3 text-gray-600 dark:text-gray-300">Staff IN on Attendance (live or batch save)</td>'
            .'<td class="px-4 py-3 text-gray-600 dark:text-gray-300"><strong>Manual IN</strong> (falls back to Biometric IN)</td></tr>'
            .'<tr><td class="px-4 py-3 font-semibold text-rose-700 dark:text-rose-300">Manual check-out (OUT)</td>'
            .'<td class="px-4 py-3 text-gray-600 dark:text-gray-300">Staff OUT button on Attendance</td>'
            .'<td class="px-4 py-3 text-gray-600 dark:text-gray-300"><strong>Manual OUT</strong> (falls back to Biometric OUT)</td></tr>'
            .'<tr><td class="px-4 py-3 font-semibold text-gray-600 dark:text-gray-300">Absent / Leave</td>'
            .'<td class="px-4 py-3 text-gray-600 dark:text-gray-300">Staff marks A or L</td>'
            .'<td class="px-4 py-3 text-gray-500 dark:text-gray-400">Not sent automatically</td></tr>'
            .'</tbody></table></div></div>'
        );
    }

    public function renderLiveCampaignsNotice(): HtmlString
    {
        $liveCount = \App\Models\WhatsAppLiveCampaign::query()
            ->where('status', \App\Enums\WhatsAppLiveCampaignStatus::Live)
            ->count();

        if ($liveCount === 0) {
            return new HtmlString(
                '<p class="text-sm text-warning-600 dark:text-warning-400">No live campaigns yet. Create templates under '
                .e(CrmNavigation::whatsAppMenu('Templates'))
                .', then create campaigns under '
                .e(CrmNavigation::whatsAppMenu('Live campaigns'))
                .' and click <strong>Go live</strong>.</p>'
            );
        }

        return new HtmlString(
            '<p class="text-sm text-gray-600 dark:text-gray-300">'
            .$liveCount.' live campaign(s) available. Each automation below must pick one of these — the linked template and student name mapping are used when sending.</p>'
        );
    }
}
