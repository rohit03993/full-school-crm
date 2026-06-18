<?php

namespace App\Console\Commands;

use App\Services\PenaltyCalculationService;
use Illuminate\Console\Command;

class ProcessLateFees extends Command
{
    protected $signature = 'crm:process-late-fees';

    protected $description = 'Generate or update late fees for overdue fee installments';

    public function handle(PenaltyCalculationService $penalties): int
    {
        if (! $penalties->isEnabled()) {
            $this->warn('Late fees are disabled in config/fees.php.');

            return self::SUCCESS;
        }

        $result = $penalties->processOverdueInstallments();

        $this->info("Processed {$result['processed']} installment(s). Total late fees: ₹"
            .number_format($result['total_penalty'], 2));

        return self::SUCCESS;
    }
}
