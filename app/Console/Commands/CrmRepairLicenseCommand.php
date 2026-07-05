<?php

namespace App\Console\Commands;

use App\Services\LicenseService;
use App\Support\InstituteSettings;
use App\Support\SiteContent;
use Illuminate\Console\Command;

class CrmRepairLicenseCommand extends Command
{
    protected $signature = 'crm:repair-license';

    protected $description = 'Reset license to a valid full-feature pack (fixes 403 errors after deploy)';

    public function handle(LicenseService $license): int
    {
        $license->repairFullLicense();

        SiteContent::clearCache();
        InstituteSettings::clearCache();
        $this->call('config:clear');

        $this->info('License repaired: all modules enabled for '.config('license.default_valid_days', 365).' days.');
        $this->line('Reload /admin and open Website → Site Content again.');

        return self::SUCCESS;
    }
}
