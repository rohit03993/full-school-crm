<?php

namespace App\Console\Commands;

use App\Services\Punch\PunchAttendanceProcessor;
use Illuminate\Console\Command;

class ProcessPunchAttendanceCommand extends Command
{
    protected $signature = 'attendance:process-punches {--continuous : Keep polling} {--interval=30 : Seconds between runs when continuous}';

    protected $description = 'Process EasyTimePro punch_logs, sync batch attendance, and queue punch WhatsApp alerts';

    public function handle(PunchAttendanceProcessor $processor): int
    {
        $continuous = (bool) $this->option('continuous');
        $interval = max(5, (int) $this->option('interval'));

        do {
            $stats = $processor->processPending();

            if ($stats['processed'] > 0) {
                $this->info(sprintf(
                    'Processed %d punch(es) — synced %d, WhatsApp queued %d',
                    $stats['processed'],
                    $stats['synced'],
                    $stats['notified'],
                ));
            }

            if ($continuous) {
                sleep($interval);
            }
        } while ($continuous);

        return self::SUCCESS;
    }
}
