<?php

namespace App\Console\Commands;

use App\Services\OccasionWishService;
use Illuminate\Console\Command;

class SendOccasionWishesCommand extends Command
{
    protected $signature = 'crm:send-occasion-wishes';

    protected $description = 'Refresh today\'s birthday and anniversary list, then send wishes when that automation is on';

    public function handle(OccasionWishService $wishes): int
    {
        $result = $wishes->runScheduled();
        $this->info($result['message']);

        return self::SUCCESS;
    }
}
