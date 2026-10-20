<?php

namespace App\Services;

use App\Enums\WhatsAppCampaignStatus;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use InvalidArgumentException;

class MetaWhatsAppTemplateDeleteService
{
    public function __construct(
        protected MetaWhatsAppService $meta,
    ) {}

    /**
     * @return list<string>
     */
    public function blockers(MetaWhatsAppTemplate $template): array
    {
        $blockers = [];

        foreach ($template->liveCampaigns()->orderBy('name')->pluck('name') as $name) {
            $blockers[] = 'live campaign '.$name;
        }

        $local = WhatsAppTemplate::query()->where('name', $template->name)->first();

        if ($local) {
            $openCampaigns = WhatsAppCampaign::query()
                ->where('whatsapp_template_id', $local->id)
                ->whereIn('status', [
                    WhatsAppCampaignStatus::Draft->value,
                    WhatsAppCampaignStatus::Queued->value,
                    WhatsAppCampaignStatus::Running->value,
                    WhatsAppCampaignStatus::Paused->value,
                ])
                ->orderBy('name')
                ->pluck('name');

            foreach ($openCampaigns as $name) {
                $blockers[] = 'campaign '.$name;
            }
        }

        $needles = [$template->name];

        if ($local) {
            $needles[] = (string) $local->id;
            $needles[] = 'template:'.$local->id;
        }

        $settingKeys = Setting::query()
            ->whereIn('value', $needles)
            ->orderBy('key')
            ->pluck('key');

        foreach ($settingKeys as $key) {
            if (! str_contains((string) $key, 'template') && ! str_contains((string) $key, 'live_campaign')) {
                continue;
            }

            $blockers[] = $this->settingLabel((string) $key);
        }

        return array_values(array_unique($blockers));
    }

    public function confirmText(MetaWhatsAppTemplate $template): string
    {
        $lines = [
            'This removes '.$template->name.' ('.$template->language.') from WhatsApp and from this list.',
            'Messages already sent stay in the chat.',
            'WhatsApp blocks this same name for 30 days.',
            'Other templates are not changed.',
        ];
        $blockers = $this->blockers($template);

        if ($blockers !== []) {
            $lines[] = 'Delete stays blocked until you move these off this template: '.implode(', ', $blockers).'.';
        }

        return implode(' ', $lines);
    }

    public function delete(MetaWhatsAppTemplate $template): void
    {
        $blockers = $this->blockers($template);

        if ($blockers !== []) {
            throw new InvalidArgumentException(
                'This template is still used by '.implode(', ', $blockers).'. Choose another template there first. Nothing was deleted.'
            );
        }

        $name = (string) $template->name;
        $resolved = $this->meta->resolveMessageTemplateId(
            $name,
            (string) $template->language,
            data_get($template->provider_meta, 'id'),
        );

        if ($resolved['status'] === 'failed') {
            throw new InvalidArgumentException((string) ($resolved['error'] ?? 'Could not reach WhatsApp. Nothing was deleted.'));
        }

        if ($resolved['status'] === 'success') {
            $result = $this->meta->deleteMessageTemplate($name, (string) $resolved['id']);

            if ($result['status'] !== 'success' && ! $this->alreadyGone((string) ($result['error'] ?? ''))) {
                throw new InvalidArgumentException((string) ($result['error'] ?? 'WhatsApp could not delete this template. It is still in the list.'));
            }
        }

        $template->delete();

        $anotherLanguageStillApproved = MetaWhatsAppTemplate::query()
            ->where('name', $name)
            ->where('is_active', true)
            ->whereRaw('UPPER(status) = ?', ['APPROVED'])
            ->exists();

        if (! $anotherLanguageStillApproved) {
            WhatsAppTemplate::query()
                ->where('name', $name)
                ->update([
                    'is_active' => false,
                    'synced_at' => now(),
                ]);
        }
    }

    protected function alreadyGone(string $error): bool
    {
        $error = strtolower($error);

        return str_contains($error, 'does not exist')
            || str_contains($error, 'not found')
            || str_contains($error, 'unknown template');
    }

    protected function settingLabel(string $key): string
    {
        return match ($key) {
            'whatsapp.homework_template_name', 'whatsapp.homework_share_live_campaign_id' => 'Homework share',
            'whatsapp.homework_combined_template_name', 'whatsapp.homework_combined_live_campaign_id' => 'Combined homework',
            'whatsapp.homework_not_done_live_campaign_id' => 'Homework not done',
            'whatsapp.student_report_live_campaign_id' => 'Student report',
            'whatsapp.activity_marks_template_name', 'whatsapp.activity_marks_live_campaign_id' => 'Test marks',
            'whatsapp.fee_reminder_template_id', 'whatsapp.fee_reminder_live_campaign_id' => 'Fee reminder',
            'whatsapp.fee_reminder_upcoming_live_campaign_id' => 'Fee reminder (upcoming)',
            'whatsapp.fee_reminder_due_live_campaign_id' => 'Fee reminder (due)',
            'whatsapp.fee_reminder_overdue_live_campaign_id' => 'Fee reminder (overdue)',
            'whatsapp.attendance_autosend_template_id', 'whatsapp.attendance_autosend_live_campaign_id' => 'Attendance message',
            'whatsapp.punch_in_autosend_template_id', 'whatsapp.punch_in_autosend_live_campaign_id' => 'Punch in',
            'whatsapp.punch_out_autosend_template_id', 'whatsapp.punch_out_autosend_live_campaign_id' => 'Punch out',
            'whatsapp.punch_manual_in_autosend_template_id', 'whatsapp.punch_manual_in_autosend_live_campaign_id' => 'Manual punch in',
            'whatsapp.punch_manual_out_autosend_template_id', 'whatsapp.punch_manual_out_autosend_live_campaign_id' => 'Manual punch out',
            'whatsapp.staff_punch_in_autosend_live_campaign_id' => 'Staff punch in',
            'whatsapp.staff_punch_out_autosend_live_campaign_id' => 'Staff punch out',
            'whatsapp.postcall_autosend_template_id' => 'Message after a call',
            'meta_whatsapp.otp_template_name' => 'Login code',
            'meta_whatsapp.test_template_name' => 'WhatsApp test',
            default => $key,
        };
    }
}
