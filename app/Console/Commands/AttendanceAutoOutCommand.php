<?php

namespace App\Console\Commands;

use App\Services\Punch\AttendanceAutoOutService;
use Illuminate\Console\Command;

class AttendanceAutoOutCommand extends Command
{
    protected $signature = 'attendance:auto-out';

    protected $description = 'Auto check-out students still marked Inside after the configured daily cutoff (default 20:00).';

    public function handle(AttendanceAutoOutService $autoOut): int
    {
        $closed = $autoOut->applyDue();

        if ($closed === 0) {
            $this->comment('No open attendance rows due for auto check-out.');
        } else {
            $this->info("Auto check-out applied to {$closed} attendance record(s).");
        }

        return self::SUCCESS;
    }
}
