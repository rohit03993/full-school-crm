<?php

namespace App\Console\Commands;

use App\Services\StorageCleanupService;
use Illuminate\Console\Command;

class CrmCleanupCommand extends Command
{
    protected $signature = 'crm:cleanup';

    protected $description = 'Remove stale Livewire uploads and orphan CRM files (old receipts, documents, proofs).';

    public function handle(StorageCleanupService $cleanup): int
    {
        $results = $cleanup->run();

        $this->info("Removed {$results['livewire_temp']} stale temporary upload(s).");
        $this->info("Removed {$results['orphan_files']} orphan stored file(s).");

        return self::SUCCESS;
    }
}
