<?php

namespace App\Console\Commands;

use App\Services\StorageCleanupService;
use Illuminate\Console\Command;

class CrmCleanupCommand extends Command
{
    protected $signature = 'crm:cleanup';

    protected $description = 'Remove stale Livewire uploads (never receipts, ID cards, payment proofs, or student documents).';

    public function handle(StorageCleanupService $cleanup): int
    {
        $results = $cleanup->run();
        $prunedLogins = app(\App\Services\StaffLoginSessionService::class)->pruneOldSessions();

        $this->info("Removed {$results['livewire_temp']} stale temporary upload(s).");
        $this->info("Removed {$results['orphan_files']} orphan stored file(s).");
        $this->info("Removed {$prunedLogins} staff login session(s) older than ".\App\Services\StaffLoginSessionService::RETAIN_DAYS.' days.');

        return self::SUCCESS;
    }
}
