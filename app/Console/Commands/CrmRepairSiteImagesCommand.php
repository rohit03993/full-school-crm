<?php

namespace App\Console\Commands;

use App\Models\SiteGalleryItem;
use App\Services\SiteContentService;
use App\Services\SiteImageService;
use App\Support\SiteContent;
use Illuminate\Console\Command;

class CrmRepairSiteImagesCommand extends Command
{
    protected $signature = 'crm:repair-site-images';

    protected $description = 'Fix broken site images (storage link, paths, removed Unsplash URLs)';

    public function handle(SiteContentService $siteContent): int
    {
        if (SiteImageService::ensurePublicStorageLink()) {
            $this->info('public/storage link: OK');
        } else {
            $this->error('Could not create public/storage symlink. Uploaded gallery images will 404 until this exists.');
            $this->line('Run manually: php artisan storage:link');
        }

        $this->call('crm:repair-license');

        $fixed = $siteContent->repairBrokenRemoteImageUrls();

        SiteContent::clearCache();
        $this->call('config:clear');

        if ($fixed > 0) {
            $this->info("Repaired {$fixed} image path(s) in the database.");
        } else {
            $this->line('No image paths needed repair in the database.');
        }

        $missing = 0;

        foreach (SiteGalleryItem::query()->orderBy('sort_order')->get() as $item) {
            if (! SiteImageService::existsOnDisk($item->image_path)) {
                $missing++;
                $this->warn("Missing file for gallery item #{$item->id} ({$item->caption}): {$item->image_path}");
            }
        }

        if ($missing > 0) {
            $this->newLine();
            $this->warn("{$missing} gallery image(s) are still missing on disk.");
            $this->line('Re-upload them in Admin → Website → Site Content → Gallery, then Save.');
        } else {
            $this->info('All gallery images are present on disk.');
        }

        $this->newLine();
        $this->comment('Hard-refresh the public site (Ctrl+F5) after running this command.');

        return self::SUCCESS;
    }
}
