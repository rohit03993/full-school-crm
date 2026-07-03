<?php

namespace App\Services\Punch;

use App\Models\AttendancePunchWhatsappLog;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppCampaignService;
use App\Enums\LicenseFeature;
use App\Support\FeatureGate;
use App\Support\PunchWhatsAppOutcome;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class PunchWhatsAppService
{
    public function __construct(
        protected WhatsAppCampaignService $campaigns,
    ) {}

    public function maybeSendForPunch(
        Student $student,
        string $enrollmentNumber,
        string $date,
        string $punchTime,
        string $state,
        ?User $staff = null,
    ): bool {
        return $this->outcomeForPunch($student, $enrollmentNumber, $date, $punchTime, $state, $staff)['queued'];
    }

    /**
     * @return array{queued: bool, message: string}
     */
    public function outcomeForPunch(
        Student $student,
        string $enrollmentNumber,
        string $date,
        string $punchTime,
        string $state,
        ?User $staff = null,
    ): array {
        try {
            if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
                return PunchWhatsAppOutcome::skipped('WhatsApp is not enabled on your licence.');
            }

            if (! $this->punchAutosendEnabled()) {
                return PunchWhatsAppOutcome::skipped('Turn on punch WhatsApp in Settings → WhatsApp Settings.');
            }

            $isManual = $staff !== null;
            $template = $this->templateForState($state, $isManual);

            if (! $template) {
                $label = $isManual ? 'Manual '.$state : 'Biometric '.$state;

                return PunchWhatsAppOutcome::skipped("No {$label} WhatsApp template selected in Settings.");
            }

            if (blank($student->mobile)) {
                return PunchWhatsAppOutcome::skipped('Student has no parent mobile number on file.');
            }

            if ($this->alreadySent($enrollmentNumber, $date, $punchTime, $state, (string) $student->mobile)) {
                return PunchWhatsAppOutcome::skipped('WhatsApp for this exact punch was already queued.');
            }

            $punchAt = Carbon::parse($date.' '.(strlen($punchTime) === 5 ? $punchTime.':00' : $punchTime));
            $staff ??= User::query()->where('is_active', true)->first();

            if (! $staff) {
                return PunchWhatsAppOutcome::skipped('No active staff user to queue the campaign.');
            }

            $channelLabel = $isManual ? 'Manual' : 'Biometric';

            $campaign = $this->campaigns->createCampaign([
                'name' => $channelLabel.' '.$state.' · '.$enrollmentNumber.' · '.$punchAt->format('d M H:i'),
                'whatsapp_template_id' => $template->id,
                'student_ids' => [$student->id],
                'campaign_variables' => [
                    'audience_source' => $isManual ? 'punch_manual' : 'punch_biometric',
                    'punch_state' => $state,
                    'punch_channel' => $isManual ? 'manual' : 'biometric',
                    'attendance_date' => $date,
                    'date' => $date,
                    'time' => $punchAt->format('H:i:s'),
                    '_student_attendance_status' => [
                        $student->id => $state === 'IN' ? 'Present' : 'Checked out',
                    ],
                ],
            ], $staff);

            $this->campaigns->queueCampaign($campaign, $staff);

            AttendancePunchWhatsappLog::query()->create([
                'student_id' => $student->id,
                'enrollment_number' => $enrollmentNumber,
                'state' => $state,
                'punch_date' => $date,
                'punch_time' => $punchAt->format('H:i:s'),
                'phone' => (string) $student->mobile,
                'status' => 'queued',
                'sent_at' => now(),
            ]);

            return PunchWhatsAppOutcome::queued('Parent WhatsApp queued — runs when the queue worker is active.');
        } catch (\Throwable $e) {
            Log::warning('Punch WhatsApp failed: '.$e->getMessage());

            return PunchWhatsAppOutcome::skipped('Could not queue WhatsApp: '.$e->getMessage());
        }
    }

    public function punchAutosendEnabled(): bool
    {
        $punchSetting = Setting::getValue('whatsapp.punch_autosend_enabled');

        if ($punchSetting !== null && $punchSetting !== '') {
            return $punchSetting === '1' || $punchSetting === 1 || $punchSetting === true;
        }

        $batchSetting = Setting::getValue('whatsapp.attendance_autosend_enabled');

        return $batchSetting === '1' || $batchSetting === 1 || $batchSetting === true;
    }

    private function templateForState(string $state, bool $isManual): ?WhatsAppTemplate
    {
        $templateId = $isManual
            ? $this->manualTemplateIdForState($state)
            : $this->biometricTemplateIdForState($state);

        if (! $templateId) {
            $templateId = $isManual
                ? $this->biometricTemplateIdForState($state)
                : null;
        }

        if (! $templateId) {
            $templateId = Setting::getValue('whatsapp.attendance_autosend_template_id');
        }

        if (! $templateId) {
            return null;
        }

        return WhatsAppTemplate::query()
            ->whereKey($templateId)
            ->where('is_active', true)
            ->first();
    }

    private function biometricTemplateIdForState(string $state): ?string
    {
        $id = $state === 'OUT'
            ? Setting::getValue('whatsapp.punch_out_autosend_template_id')
            : Setting::getValue('whatsapp.punch_in_autosend_template_id');

        return filled($id) ? (string) $id : null;
    }

    private function manualTemplateIdForState(string $state): ?string
    {
        $id = $state === 'OUT'
            ? Setting::getValue('whatsapp.punch_manual_out_autosend_template_id')
            : Setting::getValue('whatsapp.punch_manual_in_autosend_template_id');

        return filled($id) ? (string) $id : null;
    }

    private function alreadySent(string $roll, string $date, string $time, string $state, string $phone): bool
    {
        $normalizedTime = strlen($time) === 5 ? $time.':00' : $time;

        return AttendancePunchWhatsappLog::query()
            ->where('enrollment_number', $roll)
            ->whereDate('punch_date', $date)
            ->where('punch_time', $normalizedTime)
            ->where('state', $state)
            ->where('phone', $phone)
            ->exists();
    }
}
