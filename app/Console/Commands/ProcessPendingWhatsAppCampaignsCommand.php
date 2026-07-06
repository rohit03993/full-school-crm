<?php

namespace App\Console\Commands;

use App\Enums\WhatsAppCampaignStatus;
use App\Enums\WhatsAppRecipientStatus;
use App\Models\WhatsAppCampaign;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class ProcessPendingWhatsAppCampaignsCommand extends Command
{
    protected $signature = 'whatsapp:process-pending {--limit=5 : Max campaigns per run}';

    protected $description = 'Send queued WhatsApp campaigns that still have pending recipients';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));

        $campaignIds = WhatsAppCampaign::query()
            ->whereIn('status', [
                WhatsAppCampaignStatus::Queued,
                WhatsAppCampaignStatus::Running,
            ])
            ->whereHas('recipients', fn ($query) => $query->whereIn('status', [
                WhatsAppRecipientStatus::Pending,
                WhatsAppRecipientStatus::Processing,
            ]))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($campaignIds->isEmpty()) {
            return self::SUCCESS;
        }

        foreach ($campaignIds as $campaignId) {
            $exitCode = Artisan::call('whatsapp:run-campaign', ['campaign' => $campaignId]);

            $output = trim(Artisan::output());

            if ($output !== '') {
                $this->line($output);
            }

            if ($exitCode !== self::SUCCESS) {
                return $exitCode;
            }
        }

        return self::SUCCESS;
    }
}
