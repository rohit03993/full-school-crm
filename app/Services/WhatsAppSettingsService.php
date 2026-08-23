<?php

namespace App\Services;

use App\Filament\Pages\AttendancePage;
use App\Filament\Pages\ManageAttendanceBiometricPage;
use App\Filament\Pages\ManageWhatsAppSettings;
use App\Filament\Pages\StaffAttendancePage;
use App\Models\Setting;
use App\Models\WhatsAppTemplate;
use App\Support\CrmNavigation;
use App\Support\FeeReminderWhatsAppTemplate;
use App\Support\HomeworkNotDoneWhatsAppTemplate;
use App\Support\StaffPunchWhatsAppTemplate;
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
            'homework_not_done_autosend_enabled' => (bool) Setting::getValue('whatsapp.homework_not_done_autosend_enabled', false),
            'homework_not_done_live_campaign_id' => Setting::getValue('whatsapp.homework_not_done_live_campaign_id'),
            'attendance_autosend_enabled' => (bool) Setting::getValue('whatsapp.attendance_autosend_enabled', false),
            'attendance_autosend_live_campaign_id' => Setting::getValue('whatsapp.attendance_autosend_live_campaign_id'),
            'punch_autosend_enabled' => (bool) Setting::getValue('whatsapp.punch_autosend_enabled', true),
            'punch_in_autosend_live_campaign_id' => Setting::getValue('whatsapp.punch_in_autosend_live_campaign_id'),
            'punch_out_autosend_live_campaign_id' => Setting::getValue('whatsapp.punch_out_autosend_live_campaign_id'),
            'punch_manual_in_autosend_live_campaign_id' => Setting::getValue('whatsapp.punch_manual_in_autosend_live_campaign_id'),
            'punch_manual_out_autosend_live_campaign_id' => Setting::getValue('whatsapp.punch_manual_out_autosend_live_campaign_id'),
            'staff_punch_autosend_enabled' => (bool) Setting::getValue('whatsapp.staff_punch_autosend_enabled', false),
            'staff_punch_in_autosend_live_campaign_id' => Setting::getValue('whatsapp.staff_punch_in_autosend_live_campaign_id'),
            'staff_punch_out_autosend_live_campaign_id' => Setting::getValue('whatsapp.staff_punch_out_autosend_live_campaign_id'),
            'campaign_batch_size' => (int) Setting::getValue('whatsapp.campaign_batch_size', config('whatsapp.batch_size', 10)),
            'campaign_batch_delay_seconds' => (int) Setting::getValue(
                'whatsapp.campaign_next_batch_delay_seconds',
                config('whatsapp.next_batch_delay_seconds', 2),
            ),
        ];
    }

    /**
     * Channels staff can turn on/off under Automations.
     *
     * @return list<array{key: string, label: string, hint: string, enabled: bool}>
     */
    public function sendingChannelStatuses(): array
    {
        return [
            [
                'key' => 'punch',
                'label' => 'Attendance to parents',
                'hint' => 'Gate / biometric / staff IN and OUT',
                'enabled' => (bool) Setting::getValue('whatsapp.punch_autosend_enabled', true),
            ],
            [
                'key' => 'attendance_legacy',
                'label' => 'Old roll-call attendance',
                'hint' => 'Legacy batch-save only — leave off unless you still use it',
                'enabled' => (bool) Setting::getValue('whatsapp.attendance_autosend_enabled', false),
            ],
            [
                'key' => 'staff_punch',
                'label' => 'Attendance to staff',
                'hint' => 'Staff phone on IN and OUT — not parents',
                'enabled' => (bool) Setting::getValue('whatsapp.staff_punch_autosend_enabled', false),
            ],
            [
                'key' => 'fees',
                'label' => 'Fee reminders',
                'hint' => 'Daily overdue installment messages',
                'enabled' => (bool) Setting::getValue('whatsapp.fee_reminder_autosend_enabled', false),
            ],
            [
                'key' => 'homework',
                'label' => 'Homework not done',
                'hint' => 'When staff submit Not Done on Homework check',
                'enabled' => (bool) Setting::getValue('whatsapp.homework_not_done_autosend_enabled', false),
            ],
            [
                'key' => 'postcall',
                'label' => 'After a logged call',
                'hint' => 'Leads / follow-up after an outgoing call',
                'enabled' => (bool) Setting::getValue('whatsapp.postcall_autosend_enabled', false),
            ],
        ];
    }

    public function renderSendingChannelsSummary(): HtmlString
    {
        $automationsUrl = e(ManageWhatsAppSettings::getUrl());
        $masterOn = app(WhatsAppProviderResolver::class)->isMetaActive();
        $rows = '';

        foreach ($this->sendingChannelStatuses() as $channel) {
            $on = $channel['enabled'];
            $badgeClass = $on
                ? 'bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-200'
                : 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300';
            $badge = $on ? 'Sending' : 'Off';

            $rows .= '<tr class="border-t border-gray-100 dark:border-white/10">'
                .'<td class="px-3 py-2.5"><p class="font-medium text-gray-950 dark:text-white">'.e($channel['label']).'</p>'
                .'<p class="text-xs text-gray-500 dark:text-gray-400">'.e($channel['hint']).'</p></td>'
                .'<td class="px-3 py-2.5 text-right"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold '.$badgeClass.'">'.$badge.'</span></td>'
                .'</tr>';
        }

        $masterNote = $masterOn
            ? 'WhatsApp is <strong>on</strong> for this institute. The list below is what actually goes out. Change any row on Automations.'
            : 'WhatsApp is <strong>off</strong> on this page — nothing below will send until you turn <strong>WhatsApp enabled</strong> on.';

        return new HtmlString(
            '<div class="overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">'
            .'<div class="flex flex-wrap items-start justify-between gap-2 border-b border-gray-200 bg-gray-50 px-3 py-3 dark:border-white/10 dark:bg-white/5">'
            .'<div><p class="text-sm font-semibold text-gray-950 dark:text-white">What is sending</p>'
            .'<p class="mt-1 text-sm text-gray-600 dark:text-gray-300">'.$masterNote.'</p></div>'
            .'<a href="'.$automationsUrl.'" class="shrink-0 text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400">Open Automations</a>'
            .'</div>'
            .'<table class="w-full text-sm"><tbody>'.$rows.'</tbody></table>'
            .'<p class="border-t border-gray-200 px-3 py-2 text-xs text-gray-500 dark:border-white/10 dark:text-gray-400">'
            .'Share homework, exam marks, and bulk campaigns are sent only when staff click Send — they are not in this list.'
            .'</p></div>'
        );
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
            'whatsapp.homework_not_done_autosend_enabled',
            ! empty($data['homework_not_done_autosend_enabled']) ? '1' : '0',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.homework_not_done_live_campaign_id',
            filled($data['homework_not_done_live_campaign_id'] ?? null) ? (string) $data['homework_not_done_live_campaign_id'] : '',
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
            'whatsapp.staff_punch_autosend_enabled',
            ! empty($data['staff_punch_autosend_enabled']) ? '1' : '0',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.staff_punch_in_autosend_live_campaign_id',
            filled($data['staff_punch_in_autosend_live_campaign_id'] ?? null) ? (string) $data['staff_punch_in_autosend_live_campaign_id'] : '',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.staff_punch_out_autosend_live_campaign_id',
            filled($data['staff_punch_out_autosend_live_campaign_id'] ?? null) ? (string) $data['staff_punch_out_autosend_live_campaign_id'] : '',
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

    public function renderFeeReminderTemplateGuide(): HtmlString
    {
        $templatesUrl = e(\App\Filament\Resources\MetaWhatsAppTemplates\MetaWhatsAppTemplateResource::getUrl('create'));
        $campaignsUrl = e(\App\Filament\Resources\WhatsAppLiveCampaigns\WhatsAppLiveCampaignResource::getUrl('create'));
        $body = e(FeeReminderWhatsAppTemplate::BODY);
        $name = e(FeeReminderWhatsAppTemplate::NAME);

        $mappingRows = '';
        foreach (FeeReminderWhatsAppTemplate::variables() as $index => $variable) {
            $mappingRows .= '<tr class="'.($index % 2 === 0 ? 'bg-white/40 dark:bg-transparent' : '').'">'
                .'<td class="px-4 py-2 font-mono text-xs">{{'.$index.'}}</td>'
                .'<td class="px-4 py-2">'.e($variable['label']).'</td>'
                .'<td class="px-4 py-2 font-mono text-xs">'.e($variable['crm_source']).'</td>'
                .'<td class="px-4 py-2 text-gray-500">'.e($variable['example']).'</td>'
                .'</tr>';
        }

        return new HtmlString(
            '<div class="overflow-hidden rounded-xl border border-primary-200/60 bg-primary-50/40 dark:border-primary-500/20 dark:bg-primary-500/5">'
            .'<div class="border-b border-primary-200/60 px-4 py-3 dark:border-primary-500/20">'
            .'<p class="text-sm font-bold text-gray-950 dark:text-white">Required Meta template (copy this)</p>'
            .'<p class="mt-1 text-xs text-gray-600 dark:text-gray-300">'
            .'1) <a href="'.$templatesUrl.'" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">WhatsApp → Templates → New</a> '
            .'— name <code class="text-xs">'.$name.'</code>, category <strong>Utility</strong>. '
            .'2) After Meta approves, <a href="'.$campaignsUrl.'" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Quick campaigns → New</a> '
            .'— map variables below, Go live. 3) Select that live campaign in the field below.</p></div>'
            .'<div class="px-4 py-3"><pre class="whitespace-pre-wrap rounded-lg border border-gray-200 bg-white p-3 text-xs text-gray-800 dark:border-white/10 dark:bg-black/20 dark:text-gray-100">'.$body.'</pre></div>'
            .'<div class="overflow-x-auto border-t border-primary-200/60 dark:border-primary-500/20">'
            .'<table class="w-full min-w-[36rem] text-left text-sm">'
            .'<thead class="bg-white/60 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-black/20 dark:text-gray-400">'
            .'<tr><th class="px-4 py-2">Var</th><th class="px-4 py-2">Meaning</th><th class="px-4 py-2">CRM map</th><th class="px-4 py-2">Meta sample</th></tr></thead>'
            .'<tbody class="divide-y divide-primary-100 dark:divide-primary-500/10">'.$mappingRows.'</tbody></table></div></div>'
        );
    }

    public function renderHomeworkNotDoneTemplateGuide(): HtmlString
    {
        $templatesUrl = e(\App\Filament\Resources\MetaWhatsAppTemplates\MetaWhatsAppTemplateResource::getUrl('create'));
        $campaignsUrl = e(\App\Filament\Resources\WhatsAppLiveCampaigns\WhatsAppLiveCampaignResource::getUrl('create'));
        $checkUrl = e(\App\Filament\Pages\HomeworkCheckPage::getUrl());
        $body = e(HomeworkNotDoneWhatsAppTemplate::BODY);
        $name = e(HomeworkNotDoneWhatsAppTemplate::NAME);

        $mappingRows = '';
        foreach (HomeworkNotDoneWhatsAppTemplate::variables() as $index => $variable) {
            $mappingRows .= '<tr class="'.($index % 2 === 0 ? 'bg-white/40 dark:bg-transparent' : '').'">'
                .'<td class="px-4 py-2 font-mono text-xs">{{'.$index.'}}</td>'
                .'<td class="px-4 py-2">'.e($variable['label']).'</td>'
                .'<td class="px-4 py-2 font-mono text-xs">'.e($variable['crm_source']).'</td>'
                .'<td class="px-4 py-2 text-gray-500">'.e($variable['example']).'</td>'
                .'</tr>';
        }

        return new HtmlString(
            '<div class="overflow-hidden rounded-xl border border-sky-200/60 bg-sky-50/40 dark:border-sky-500/20 dark:bg-sky-500/5">'
            .'<div class="border-b border-sky-200/60 px-4 py-3 dark:border-sky-500/20">'
            .'<p class="text-sm font-bold text-gray-950 dark:text-white">Required Meta template — Homework not done</p>'
            .'<p class="mt-1 text-xs text-gray-600 dark:text-gray-300">'
            .'Separate from share-homework and attendance templates. '
            .'1) <a href="'.$templatesUrl.'" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Templates → New</a> '
            .'name <code class="text-xs">'.$name.'</code>, Utility. '
            .'2) Live quick campaign with mapping below. '
            .'3) Teachers open <a href="'.$checkUrl.'" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Academics → Homework check</a>, select students, Submit Not Done, then confirm WhatsApp count.</p></div>'
            .'<div class="px-4 py-3"><pre class="whitespace-pre-wrap rounded-lg border border-gray-200 bg-white p-3 text-xs text-gray-800 dark:border-white/10 dark:bg-black/20 dark:text-gray-100">'.$body.'</pre></div>'
            .'<div class="overflow-x-auto border-t border-sky-200/60 dark:border-sky-500/20">'
            .'<table class="w-full min-w-[36rem] text-left text-sm">'
            .'<thead class="bg-white/60 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-black/20 dark:text-gray-400">'
            .'<tr><th class="px-4 py-2">Var</th><th class="px-4 py-2">Meaning</th><th class="px-4 py-2">CRM map</th><th class="px-4 py-2">Meta sample</th></tr></thead>'
            .'<tbody class="divide-y divide-sky-100 dark:divide-sky-500/10">'.$mappingRows.'</tbody></table></div></div>'
        );
    }

    public function renderStaffPunchAutomationGuide(): HtmlString
    {
        $templatesUrl = e(\App\Filament\Resources\MetaWhatsAppTemplates\MetaWhatsAppTemplateResource::getUrl('create'));
        $campaignsUrl = e(\App\Filament\Resources\WhatsAppLiveCampaigns\WhatsAppLiveCampaignResource::getUrl('create'));
        $staffUrl = e(StaffAttendancePage::getUrl());
        $inName = e(StaffPunchWhatsAppTemplate::IN_NAME);
        $outName = e(StaffPunchWhatsAppTemplate::OUT_NAME);
        $inBody = e(StaffPunchWhatsAppTemplate::IN_BODY);
        $outBody = e(StaffPunchWhatsAppTemplate::OUT_BODY);

        $mappingRows = '';
        foreach (StaffPunchWhatsAppTemplate::variables() as $index => $variable) {
            $mappingRows .= '<tr class="'.($index % 2 === 0 ? 'bg-white/40 dark:bg-transparent' : '').'">'
                .'<td class="px-4 py-2 font-mono text-xs">{{'.$index.'}}</td>'
                .'<td class="px-4 py-2">'.e($variable['label']).'</td>'
                .'<td class="px-4 py-2 font-mono text-xs">'.e($variable['crm_source']).'</td>'
                .'<td class="px-4 py-2 text-gray-500">'.e($variable['example']).'</td>'
                .'</tr>';
        }

        return new HtmlString(
            '<div class="overflow-hidden rounded-xl border border-sky-200/60 bg-sky-50/40 dark:border-sky-500/20 dark:bg-sky-500/5">'
            .'<div class="border-b border-sky-200/60 px-4 py-3 dark:border-sky-500/20">'
            .'<p class="text-sm font-bold text-gray-950 dark:text-white">Staff IN/OUT WhatsApp — not parent templates</p>'
            .'<p class="mt-1 text-xs text-gray-600 dark:text-gray-300">'
            .'Goes to the staff mobile on file. Auto-out at night does <strong>not</strong> send. '
            .'1) <a href="'.$templatesUrl.'" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Templates → New</a> '
            .'name <code class="text-xs">'.$inName.'</code> and <code class="text-xs">'.$outName.'</code> (Utility), submit, Sync after Meta approves. '
            .'2) <a href="'.$campaignsUrl.'" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Live campaigns</a> — one per template, map vars below, Go live. '
            .'3) Pick those campaigns here and turn the switch on. Check names on <a href="'.$staffUrl.'" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Staff attendance</a>.</p></div>'
            .'<div class="grid gap-3 px-4 py-3 sm:grid-cols-2">'
            .'<div><p class="mb-1 text-[11px] font-bold uppercase tracking-wide text-gray-500">'.$inName.'</p>'
            .'<pre class="whitespace-pre-wrap rounded-lg border border-gray-200 bg-white p-3 text-xs text-gray-800 dark:border-white/10 dark:bg-black/20 dark:text-gray-100">'.$inBody.'</pre></div>'
            .'<div><p class="mb-1 text-[11px] font-bold uppercase tracking-wide text-gray-500">'.$outName.'</p>'
            .'<pre class="whitespace-pre-wrap rounded-lg border border-gray-200 bg-white p-3 text-xs text-gray-800 dark:border-white/10 dark:bg-black/20 dark:text-gray-100">'.$outBody.'</pre></div>'
            .'</div>'
            .'<div class="overflow-x-auto border-t border-sky-200/60 dark:border-sky-500/20">'
            .'<table class="w-full min-w-[36rem] text-left text-sm">'
            .'<thead class="bg-white/60 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-black/20 dark:text-gray-400">'
            .'<tr><th class="px-4 py-2">Var</th><th class="px-4 py-2">Meaning</th><th class="px-4 py-2">CRM map</th><th class="px-4 py-2">Meta sample</th></tr></thead>'
            .'<tbody class="divide-y divide-sky-100 dark:divide-sky-500/10">'.$mappingRows.'</tbody></table></div></div>'
        );
    }
}
