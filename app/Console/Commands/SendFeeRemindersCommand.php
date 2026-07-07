<?php

namespace App\Console\Commands;

use App\Services\FeeReminderWhatsAppService;
use Illuminate\Console\Command;

class SendFeeRemindersCommand extends Command
{
    protected $signature = 'crm:send-fee-reminders';

    protected $description = 'Queue WhatsApp fee reminders for overdue installments using an approved template';

    public function handle(FeeReminderWhatsAppService $reminders): int
    {
        $result = $reminders->maybeQueueDailyReminders();

        if (filled($result['reason'] ?? null) && ($result['queued'] ?? 0) === 0) {
            $this->warn($result['reason']);
        }

        $this->info("Queued {$result['queued']} fee reminder(s). Skipped {$result['skipped']}.");

        return self::SUCCESS;
    }
}
