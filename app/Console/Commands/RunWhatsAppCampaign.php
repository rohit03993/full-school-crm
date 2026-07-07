<?php

namespace App\Console\Commands;

use App\Enums\WhatsAppAutoStatus;
use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppMessageSource;
use App\Enums\WhatsAppRecipientStatus;
use App\Jobs\RunWhatsAppCampaignJob;
use App\Models\MetaWhatsAppMessage;
use App\Models\Setting;
use App\Models\StudentCall;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use App\Models\MetaWhatsAppTemplate;
use App\Services\MetaWhatsAppService;
use App\Services\WhatsAppAnalyticsService;
use App\Services\WhatsAppDispatchService;
use App\Services\WhatsAppTemplateParamResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunWhatsAppCampaign extends Command
{
    protected $signature = 'whatsapp:run-campaign {campaign : Campaign ID} {--batch= : Max recipients per run}';

    protected $description = 'Send pending WhatsApp messages for a campaign via Meta Cloud API';

    public function handle(
        WhatsAppDispatchService $whatsapp,
        WhatsAppTemplateParamResolver $paramResolver,
        WhatsAppAnalyticsService $analytics,
    ): int {
        set_time_limit(0);

        $campaign = WhatsAppCampaign::query()
            ->with([
                'template',
                'batch',
                'course',
                'recipients.student.enquiries.course',
                'recipients.student.activeEnrollment.course',
                'recipients.student.activeBatchStudent.batch',
                'shotBy',
            ])
            ->findOrFail((int) $this->argument('campaign'));

        $batchSize = (int) ($this->option('batch')
            ?: Setting::getValue('whatsapp.campaign_batch_size', config('whatsapp.batch_size', 10)));

        if ($batchSize < 1) {
            $batchSize = 10;
        }

        $batchSize = min(50, $batchSize);

        if ($campaign->status === WhatsAppCampaignStatus::Completed) {
            $this->info('Campaign already completed.');

            return self::SUCCESS;
        }

        if ($campaign->status === WhatsAppCampaignStatus::Paused) {
            $this->info('Campaign is paused.');

            return self::SUCCESS;
        }

        if ($campaign->status === WhatsAppCampaignStatus::Draft) {
            $campaign->update(['status' => WhatsAppCampaignStatus::Queued]);
        }

        if (! $campaign->started_at) {
            $campaign->update(['started_at' => now()]);
        }

        if (! $whatsapp->isConfigured()) {
            $error = $whatsapp->configurationError();
            $this->error($error);
            $this->failPendingRecipients($campaign, $error);

            return self::FAILURE;
        }

        $template = $campaign->template;
        $template->ensureParamMappings();
        $sources = $template->paramSources();
        $sender = $campaign->shotBy ?? ($campaign->created_by ? User::find($campaign->created_by) : null);
        $claimedIds = $this->claimBatchRecipientIds($campaign->id, $batchSize);
        $recipients = WhatsAppCampaignRecipient::query()->whereIn('id', $claimedIds)->cursor();

        $sent = 0;
        $failed = 0;
        $processed = 0;

        foreach ($recipients as $recipient) {
            $recipient->load([
                'student.enquiries.course',
                'student.activeEnrollment.course',
                'student.enrollments',
                'student.activeBatchStudent.batch',
            ]);
            $student = $recipient->student;

            if (! $student) {
                $recipient->update([
                    'status' => WhatsAppRecipientStatus::Failed,
                    'error_message' => 'Student not found.',
                ]);
                $failed++;

                continue;
            }

            $templateParams = $paramResolver->resolveAll(
                $sources,
                $student,
                $sender,
                $student->enquiries->first(),
                $campaign,
            );

            $expectedParamCount = $this->expectedTemplateParamCount($template, $campaign);

            $templateParams = MetaWhatsAppService::normalizeBodyParams(
                $templateParams,
                $expectedParamCount,
            );

            if ($expectedParamCount > 0 && ! $paramResolver->hasResolvableParams($templateParams, $campaign)) {
                $recipient->update([
                    'status' => WhatsAppRecipientStatus::Failed,
                    'template_params' => $templateParams,
                    'error_message' => 'Fill in the template fields before sending.',
                ]);
                $failed++;

                continue;
            }

            $languageCode = data_get($template->provider_meta, 'meta_language');
            $languageCode = is_string($languageCode) && $languageCode !== '' ? $languageCode : null;

            $result = $whatsapp->send(
                $recipient->phone,
                $templateParams,
                $template->name,
                (string) ($student->name ?? 'User'),
                $expectedParamCount,
                $languageCode,
                [
                    'message_source' => WhatsAppMessageSource::Campaign->value,
                    'whatsapp_campaign_recipient_id' => $recipient->id,
                    'student_id' => $student->id,
                ],
            );

            $recipient->template_params = $templateParams;
            $recipient->message_sent = $paramResolver->buildPreview($template->body, $templateParams);
            $recipient->provider_response = [
                'provider' => $result['provider'] ?? 'meta',
                'response' => $result['response'] ?? null,
            ];

            if ($result['status'] === 'success') {
                $recipient->status = WhatsAppRecipientStatus::Sent;
                $recipient->error_message = null;

                if (filled($result['message_id'] ?? null)) {
                    $metaMessage = MetaWhatsAppMessage::query()
                        ->where('wamid', (string) $result['message_id'])
                        ->first();

                    if ($metaMessage) {
                        $recipient->wamid = (string) $result['message_id'];
                        $recipient->meta_whatsapp_message_id = $metaMessage->id;
                        $recipient->estimated_cost_inr = $metaMessage->estimated_cost_inr;
                    }
                }

                $sent++;
            } else {
                $recipient->status = WhatsAppRecipientStatus::Failed;
                $recipient->error_message = $result['error'] ?? 'Unknown error';
                $failed++;
            }

            $recipient->save();

            if ($recipient->student_call_id) {
                StudentCall::query()
                    ->whereKey($recipient->student_call_id)
                    ->update([
                        'whatsapp_auto_status' => $recipient->status === WhatsAppRecipientStatus::Sent
                            ? WhatsAppAutoStatus::Success
                            : WhatsAppAutoStatus::Failed,
                    ]);
            }

            $processed++;
            $this->maybePauseAfterChunk($processed);
        }

        $sentCount = WhatsAppCampaignRecipient::query()
            ->where('whatsapp_campaign_id', $campaign->id)
            ->where('status', WhatsAppRecipientStatus::Sent)
            ->count();

        $failedCount = WhatsAppCampaignRecipient::query()
            ->where('whatsapp_campaign_id', $campaign->id)
            ->where('status', WhatsAppRecipientStatus::Failed)
            ->count();

        $campaign->update([
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
        ]);

        $analytics->refreshCampaignCostTotals($campaign->fresh() ?? $campaign);

        $remaining = WhatsAppCampaignRecipient::query()
            ->where('whatsapp_campaign_id', $campaign->id)
            ->whereIn('status', [WhatsAppRecipientStatus::Pending, WhatsAppRecipientStatus::Processing])
            ->count();

        if ($remaining === 0) {
            $campaign->update([
                'status' => WhatsAppCampaignStatus::Completed,
                'finished_at' => now(),
            ]);
            $this->info('Campaign completed.');
        } else {
            $campaign->update(['status' => WhatsAppCampaignStatus::Running]);
            $delaySeconds = (int) Setting::getValue(
                'whatsapp.campaign_next_batch_delay_seconds',
                config('whatsapp.next_batch_delay_seconds', 0),
            );

            if ($delaySeconds > 0) {
                RunWhatsAppCampaignJob::dispatch($campaign->id)->delay(now()->addSeconds($delaySeconds));
            } else {
                RunWhatsAppCampaignJob::dispatch($campaign->id);
            }

            $this->info("Campaign still has {$remaining} pending recipients.");
        }

        $this->info("Sent: {$sent}, Failed: {$failed}");

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    protected function claimBatchRecipientIds(int $campaignId, int $batchSize): array
    {
        WhatsAppCampaignRecipient::query()
            ->where('whatsapp_campaign_id', $campaignId)
            ->where('status', WhatsAppRecipientStatus::Processing)
            ->where('updated_at', '<', now()->subMinutes(15))
            ->update(['status' => WhatsAppRecipientStatus::Pending]);

        return DB::transaction(function () use ($campaignId, $batchSize): array {
            $ids = WhatsAppCampaignRecipient::query()
                ->where('whatsapp_campaign_id', $campaignId)
                ->where('status', WhatsAppRecipientStatus::Pending)
                ->orderBy('id')
                ->lockForUpdate()
                ->limit($batchSize)
                ->pluck('id')
                ->all();

            if ($ids !== []) {
                WhatsAppCampaignRecipient::query()
                    ->whereIn('id', $ids)
                    ->update(['status' => WhatsAppRecipientStatus::Processing]);
            }

            return $ids;
        });
    }

    protected function expectedTemplateParamCount(\App\Models\WhatsAppTemplate $template, WhatsAppCampaign $campaign): int
    {
        $manual = $campaign->campaignVariable('_manual', []);
        $manualCount = is_array($manual) ? count($manual) : 0;

        $metaTemplate = MetaWhatsAppTemplate::query()
            ->where('name', $template->name)
            ->where('is_active', true)
            ->orderByDesc('synced_at')
            ->first();

        return max(
            (int) $template->param_count,
            $metaTemplate ? (int) $metaTemplate->param_count : 0,
            $manualCount,
            count($template->paramSources()),
        );
    }

    protected function failPendingRecipients(WhatsAppCampaign $campaign, string $error): void
    {
        $failed = WhatsAppCampaignRecipient::query()
            ->where('whatsapp_campaign_id', $campaign->id)
            ->whereIn('status', [WhatsAppRecipientStatus::Pending, WhatsAppRecipientStatus::Processing])
            ->update([
                'status' => WhatsAppRecipientStatus::Failed,
                'error_message' => $error,
            ]);

        if ($failed === 0) {
            return;
        }

        $failedCount = WhatsAppCampaignRecipient::query()
            ->where('whatsapp_campaign_id', $campaign->id)
            ->where('status', WhatsAppRecipientStatus::Failed)
            ->count();

        $sentCount = WhatsAppCampaignRecipient::query()
            ->where('whatsapp_campaign_id', $campaign->id)
            ->where('status', WhatsAppRecipientStatus::Sent)
            ->count();

        $campaign->update([
            'status' => WhatsAppCampaignStatus::Completed,
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
            'finished_at' => now(),
        ]);
    }

    protected function maybePauseAfterChunk(int $processed): void
    {
        $every = (int) config('whatsapp.pause_after_messages', 0);

        if ($every < 1 || $processed % $every !== 0) {
            return;
        }

        $seconds = (float) config('whatsapp.pause_seconds', 0);
        $micros = (int) round($seconds * 1_000_000);

        if ($micros > 0) {
            usleep($micros);
        }
    }
}
