<?php

namespace Tests\Feature;

use App\Services\StorageCleanupService;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StorageCleanupTest extends TestCase
{
    public function test_replace_stored_file_deletes_previous_path(): void
    {
        Storage::fake(StorageCleanupService::DISK);

        Storage::disk(StorageCleanupService::DISK)->put('id_cards/old.pdf', 'old');
        Storage::disk(StorageCleanupService::DISK)->put('id_cards/new.pdf', 'stale-target');

        app(StorageCleanupService::class)->replaceStoredFile('id_cards/old.pdf', 'id_cards/new.pdf');

        Storage::disk(StorageCleanupService::DISK)->assertMissing('id_cards/old.pdf');
        Storage::disk(StorageCleanupService::DISK)->assertMissing('id_cards/new.pdf');
    }
}
