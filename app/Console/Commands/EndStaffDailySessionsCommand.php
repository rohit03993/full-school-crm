<?php

namespace App\Console\Commands;

use App\Services\StaffDailySessionService;
use Illuminate\Console\Command;

class EndStaffDailySessionsCommand extends Command
{
    protected $signature = 'staff:end-daily-sessions';

    protected $description = 'Mark staff login-log rows as ended after the daily 8:00 PM IST cutoff.';

    public function handle(StaffDailySessionService $sessions): int
    {
        $closed = $sessions->closeExpiredLoginLogs();

        if ($closed === 0) {
            $this->comment('No open staff logins due for the daily 8:00 PM IST cutoff.');
        } else {
            $this->info("Closed {$closed} staff login log row(s) at the daily cutoff.");
        }

        return self::SUCCESS;
    }
}
