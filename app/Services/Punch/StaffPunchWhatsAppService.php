<?php

namespace App\Services\Punch;

use App\Enums\LicenseFeature;
use App\Models\Setting;
use App\Models\StaffPunchWhatsappLog;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppDispatchService;
use App\Services\WhatsAppSettingsService;
use App\Services\WhatsAppTemplateParamResolver;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use App\Support\PunchWhatsAppOutcome;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class StaffPunchWhatsAppService
{
    public function __construct(
        protected WhatsAppDispatchService $dispatch,
        protected WhatsAppTemplateParamResolver $params,
    ) {}

    /**
     * @return array{queued: bool, message: string}
     */
    public function outcomeForPunch(
        User $staffMember,
        string $employeeCode,
        string $date,
        string $punchTime,
        string $state,
        ?User $actor = null,
    ): array {
        try {
            if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
                return PunchWhatsAppOutcome::skipped('WhatsApp is not enabled on your licence.');
            }

            if (! $this->punchAutosendEnabled()) {
                return PunchWhatsAppOutcome::skipped(
                    'Turn on staff punch WhatsApp in '.CrmNavigation::whatsAppMenu('Automations').'.'
                );
            }

            $template = $this->templateForState($state);

            if (! $template) {
                return PunchWhatsAppOutcome::skipped('No staff '.$state.' WhatsApp campaign selected in Automations.');
            }

            $phone = $this->staffMobile($staffMember);

            if (blank($phone)) {
                return PunchWhatsAppOutcome::skipped('Staff has no mobile number on file.');
            }

            if ($this->alreadySent($employeeCode, $date, $punchTime, $state, $phone)) {
                return PunchWhatsAppOutcome::skipped('WhatsApp for this exact staff punch was already sent.');
            }

            $punchAt = Carbon::parse($date.' '.(strlen($punchTime) === 5 ? $punchTime.':00' : $punchTime));
            $sources = $template->paramSources();
            $bodyParams = $this->params->resolveAllForStaff(
                $sources,
                $staffMember,
                $employeeCode,
                $date,
                $punchAt->format('H:i:s'),
                $state,
                $actor,
            );

            $result = $this->dispatch->send(
                phone: $phone,
                templateParams: $bodyParams,
                templateName: $template->name,
                userName: $actor?->name ?? $staffMember->name,
                expectedParamCount: (int) $template->param_count,
                languageCode: data_get($template->provider_meta, 'language_code', 'en'),
                logContext: [
                    'message_source' => \App\Enums\WhatsAppMessageSource::Punch->value,
                    'source' => 'staff_punch',
                    'user_id' => $staffMember->id,
                    'employee_code' => $employeeCode,
                    'punch_state' => $state,
                ],
            );

            $status = ($result['status'] ?? '') === 'sent' || ($result['status'] ?? '') === 'queued'
                ? (($result['status'] ?? '') === 'sent' ? 'sent' : 'queued')
                : 'failed';

            StaffPunchWhatsappLog::query()->create([
                'user_id' => $staffMember->id,
                'employee_code' => $employeeCode,
                'state' => $state,
                'punch_date' => $date,
                'punch_time' => $punchAt->format('H:i:s'),
                'phone' => $phone,
                'status' => $status,
                'sent_at' => now(),
            ]);

            if ($status === 'failed') {
                return PunchWhatsAppOutcome::skipped(
                    'Staff WhatsApp failed: '.($result['error'] ?? 'Meta rejected the message.')
                );
            }

            return PunchWhatsAppOutcome::queued(
                $status === 'sent' ? 'Staff WhatsApp sent.' : 'Staff WhatsApp is being sent.'
            );
        } catch (\Throwable $e) {
            Log::warning('Staff punch WhatsApp failed: '.$e->getMessage());

            return PunchWhatsAppOutcome::skipped('Could not send staff WhatsApp: '.$e->getMessage());
        }
    }

    public function punchAutosendEnabled(): bool
    {
        $value = Setting::getValue('whatsapp.staff_punch_autosend_enabled');

        return $value === '1' || $value === 1 || $value === true;
    }

    private function templateForState(string $state): ?WhatsAppTemplate
    {
        $settings = app(WhatsAppSettingsService::class);
        $liveCampaignId = $state === 'OUT'
            ? Setting::getValue('whatsapp.staff_punch_out_autosend_live_campaign_id')
            : Setting::getValue('whatsapp.staff_punch_in_autosend_live_campaign_id');

        return $settings->resolveAutomationTemplate(
            filled($liveCampaignId) ? (string) $liveCampaignId : null,
        );
    }

    private function staffMobile(User $staffMember): string
    {
        $staffMember->loadMissing('staffProfile');

        $mobile = $staffMember->staffProfile?->mobile ?: $staffMember->mobile;

        return preg_replace('/\D/', '', (string) $mobile) ?: '';
    }

    private function alreadySent(string $code, string $date, string $time, string $state, string $phone): bool
    {
        $normalizedTime = strlen($time) === 5 ? $time.':00' : $time;

        return StaffPunchWhatsappLog::query()
            ->where('employee_code', $code)
            ->whereDate('punch_date', $date)
            ->where('punch_time', $normalizedTime)
            ->where('state', $state)
            ->where('phone', $phone)
            ->exists();
    }
}
