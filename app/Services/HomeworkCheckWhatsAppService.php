<?php

namespace App\Services;

use App\Enums\LicenseFeature;
use App\Enums\WhatsAppRecipientStatus;
use App\Models\HomeworkCheck;
use App\Models\Setting;
use App\Models\User;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Illuminate\Support\Facades\Log;

class HomeworkCheckWhatsAppService
{
    public function __construct(
        protected WhatsAppCampaignService $campaigns,
        protected WhatsAppSettingsService $settings,
    ) {}

    /**
     * @return array{queued: bool, message: string}
     */
    public function notifyNotDone(HomeworkCheck $check, User $teacher): array
    {
        try {
            if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
                return ['queued' => false, 'message' => 'WhatsApp is not enabled on your licence.'];
            }

            if (! Setting::getValue('whatsapp.homework_not_done_autosend_enabled')) {
                return [
                    'queued' => false,
                    'message' => 'Turn on Homework not done WhatsApp in '.CrmNavigation::whatsAppMenu('Automations').'.',
                ];
            }

            $template = $this->settings->resolveAutomationTemplate(
                Setting::getValue('whatsapp.homework_not_done_live_campaign_id'),
            );

            if (! $template) {
                return [
                    'queued' => false,
                    'message' => 'No Homework not done live campaign/template selected in Automations.',
                ];
            }

            $student = $check->student;
            $mobile = trim((string) ($check->parent_mobile ?: $student?->mobile));

            if ($mobile === '') {
                return ['queued' => false, 'message' => 'Student has no parent mobile number on file.'];
            }

            $batch = $check->batch?->loadMissing('course');
            $classSection = $batch?->displayLabel() ?? 'Class';

            $campaign = $this->campaigns->createCampaign([
                'name' => 'Homework not done · '.$student->name.' · '.now()->format('d M H:i'),
                'whatsapp_template_id' => $template->id,
                'student_ids' => [$student->id],
                'campaign_variables' => [
                    'audience_source' => 'homework_check',
                    'topic' => $check->topic,
                    'subject' => $check->subject_name,
                    'class_section' => $classSection,
                    'date' => $check->checked_on?->toDateString() ?? now()->toDateString(),
                    '_student_ids' => [$student->id],
                    '_homework_check_id' => $check->id,
                ],
            ], $teacher);

            $campaign = $this->campaigns->queueCampaign($campaign, $teacher);
            $recipient = $campaign->recipients()->first();

            if ($recipient?->status === WhatsAppRecipientStatus::Failed) {
                return [
                    'queued' => false,
                    'message' => 'WhatsApp send failed for this student.',
                ];
            }

            return [
                'queued' => true,
                'message' => 'WhatsApp queued to parent.',
            ];
        } catch (\Throwable $exception) {
            Log::warning('Homework not-done WhatsApp failed: '.$exception->getMessage());

            return ['queued' => false, 'message' => $exception->getMessage()];
        }
    }
}
