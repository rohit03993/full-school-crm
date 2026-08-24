<?php

namespace App\Console\Commands;

use App\Services\FeeReminderWhatsAppService;
use App\Services\WebPushService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendFeeRemindersCommand extends Command
{
    protected $signature = 'crm:send-fee-reminders';

    protected $description = 'Queue WhatsApp fee reminders (upcoming, due today, overdue) and PWA fee push notifications';

    public function handle(FeeReminderWhatsAppService $reminders, WebPushService $push): int
    {
        $result = $reminders->maybeQueueDailyReminders();

        if (filled($result['reason'] ?? null) && ($result['queued'] ?? 0) === 0) {
            $this->warn($result['reason']);
        }

        $this->info("Queued {$result['queued']} WhatsApp fee reminder(s). Skipped {$result['skipped']}.");

        // Push runs even when WhatsApp is off — same eligibility list.
        $pushSent = 0;
        try {
            $eligible = $reminders->eligibleStudents();
            $pushSent = $push->notifyFeeReminders(
                $eligible->map(fn (array $row): array => [
                    'student_id' => (int) $row['student_id'],
                    'pending' => $row['pending_amount'] ?? 0,
                ]),
            );
        } catch (\Throwable $exception) {
            Log::warning('Fee reminder web push failed: '.$exception->getMessage());
            $this->warn('Web push: '.$exception->getMessage());
        }

        $this->info("Sent {$pushSent} fee reminder push notification(s).");

        return self::SUCCESS;
    }
}
