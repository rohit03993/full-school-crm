<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CrmProcessQueueCommand extends Command
{
    protected $signature = 'crm:process-queue {--max-time=55 : Max seconds to process jobs}';

    protected $description = 'Drain pending Laravel queue jobs (WhatsApp campaigns, punch alerts, etc.)';

    public function handle(): int
    {
        if (config('queue.default') === 'sync') {
            $this->info('Queue connection is sync — jobs already run when dispatched.');

            return self::SUCCESS;
        }

        return $this->call('queue:work', [
            '--stop-when-empty' => true,
            '--max-time' => max(1, (int) $this->option('max-time')),
            '--tries' => 3,
        ]);
    }
}
