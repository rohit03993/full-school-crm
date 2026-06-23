<?php

namespace App\Services;

use App\Enums\CallDirection;
use App\Enums\CallStatus;
use App\Enums\WhatsAppAutoStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Jobs\RunWhatsAppCampaignJob;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentCall;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\Facades\Log;

class PostCallWhatsAppService
{
    public function __construct(
        protected WhatsAppCampaignService $campaigns,
    ) {}

    public function maybeQueueAfterConnectedCall(StudentCall $call, User $staff): void
    {
        if ($call->call_direction !== CallDirection::Outgoing || $call->call_status !== CallStatus::Connected) {
            return;
        }

        try {
            if (! Setting::getValue('whatsapp.postcall_autosend_enabled')) {
                return;
            }

            $templateId = Setting::getValue('whatsapp.postcall_autosend_template_id');

            if (! $templateId) {
                return;
            }

            $template = WhatsAppTemplate::query()
                ->whereKey($templateId)
                ->where('is_active', true)
                ->first();

            if (! $template) {
                return;
            }

            $student = $call->student ?? Student::query()->find($call->student_id);

            if (! $student || blank($student->mobile)) {
                $call->update(['whatsapp_auto_status' => WhatsAppAutoStatus::Skipped]);

                return;
            }

            $campaign = WhatsAppCampaign::query()->create([
                'whatsapp_template_id' => $template->id,
                'name' => $template->name.' · post-call',
                'status' => WhatsAppCampaignStatus::Queued,
                'total_recipients' => 1,
                'created_by' => $staff->id,
                'shot_by' => $staff->id,
                'shot_at' => now(),
            ]);

            WhatsAppCampaignRecipient::query()->create([
                'whatsapp_campaign_id' => $campaign->id,
                'student_id' => $student->id,
                'student_call_id' => $call->id,
                'phone' => (string) $student->mobile,
                'status' => WhatsAppRecipientStatus::Pending,
            ]);

            $call->update(['whatsapp_auto_status' => WhatsAppAutoStatus::Queued]);

            RunWhatsAppCampaignJob::dispatch($campaign->id);
        } catch (\Throwable $e) {
            $call->update(['whatsapp_auto_status' => WhatsAppAutoStatus::Failed]);
            Log::warning('Post-call WhatsApp failed: '.$e->getMessage());
        }
    }
}
