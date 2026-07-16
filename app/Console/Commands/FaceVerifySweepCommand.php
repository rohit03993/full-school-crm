<?php

namespace App\Console\Commands;

use App\Services\FaceVerify\FaceVerifyGateService;
use Illuminate\Console\Command;

class FaceVerifySweepCommand extends Command
{
    protected $signature = 'face-verify:sweep';

    protected $description = 'Mark timed-out Face Verify requests as TIMEOUT without writing attendance';

    public function handle(FaceVerifyGateService $gate): int
    {
        if (! $gate->isEnabled()) {
            return self::SUCCESS;
        }

        $count = $gate->markTimedOutPending();

        if ($count > 0) {
            $this->info("Marked {$count} Face Verify request(s) as TIMEOUT.");
        }

        return self::SUCCESS;
    }
}
