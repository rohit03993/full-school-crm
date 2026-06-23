<?php

namespace App\Console\Commands;

use App\Services\SiteContentService;
use App\Support\SiteContent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CrmRepairSiteImagesCommand extends Command
{
    protected $signature = 'crm:repair-site-images';

    protected $description = 'Fix broken default site images (storage link + removed Unsplash URLs)';

    public function handle(SiteContentService $siteContent): int
    {
        if (! File::exists(public_path('storage'))) {
            $this->warn('public/storage is missing — creating storage link for uploaded logos and images.');
            $this->call('storage:link');
        } else {
            $this->info('public/storage link: OK');
        }

        $fixed = $siteContent->repairBrokenRemoteImageUrls();

        SiteContent::clearCache();
        $this->call('config:clear');

        if ($fixed > 0) {
            $this->info("Replaced {$fixed} broken remote image URL(s) in the database.");
        } else {
            $this->line('No broken remote image URLs found in the database.');
        }

        $this->newLine();
        $this->comment('Hard-refresh the browser (Ctrl+F5) on http://localhost:8000');

        return self::SUCCESS;
    }
}
