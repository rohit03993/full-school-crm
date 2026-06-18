<?php

namespace App\Console\Commands;

use App\Services\SiteContentService;
use Illuminate\Console\Command;

class CrmSyncInstituteBrandingCommand extends Command
{
    protected $signature = 'crm:sync-institute-branding';

    protected $description = 'Reset public site and CRM branding from config/institute.php and .env';

    public function handle(SiteContentService $siteContent): int
    {
        $siteContent->syncDefaultsFromConfig();

        $this->info('Institute branding synced from config.');
        $this->line('Name: '.config('institute.name'));
        $this->line('Tagline: '.config('institute.tagline'));
        $this->newLine();
        $this->comment('Hard-refresh the browser (Ctrl+F5) if the homepage still looks old.');

        return self::SUCCESS;
    }
}
