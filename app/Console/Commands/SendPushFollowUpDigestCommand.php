<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\FollowUpWorklistService;
use App\Services\WebPushService;
use Illuminate\Console\Command;

class SendPushFollowUpDigestCommand extends Command
{
    protected $signature = 'crm:send-push-followup-digest';

    protected $description = 'Send a morning PWA push to staff who have follow-ups due today';

    public function handle(FollowUpWorklistService $followUps, WebPushService $push): int
    {
        if (! $push->isConfigured()) {
            $this->warn('Web push is not configured (VAPID keys / package).');

            return self::SUCCESS;
        }

        $sent = 0;
        $staff = User::query()->where('is_active', true)->where('is_platform_operator', false)->get();

        foreach ($staff as $user) {
            $due = $followUps->dueCount($user) + $followUps->dueCallFollowUpCount($user);

            if ($due < 1) {
                continue;
            }

            $result = $push->notifyStaffFollowUpDigest($user, $due);
            $sent += $result['sent'];
        }

        $this->info("Sent {$sent} follow-up digest push notification(s).");

        return self::SUCCESS;
    }
}
