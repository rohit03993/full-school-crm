<?php

namespace Tests\Unit;

use App\Services\SiteImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteImageServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_strips_storage_prefix_from_paths(): void
    {
        $this->assertSame(
            'site/gallery/photo.jpg',
            SiteImageService::normalizeStoragePath('storage/site/gallery/photo.jpg'),
        );
    }

    public function test_it_resolves_basename_only_gallery_uploads(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site/gallery/photo.jpg', 'binary');

        $this->assertSame(
            'site/gallery/photo.jpg',
            SiteImageService::resolveExistingPath('photo.jpg'),
        );

        $this->assertStringContainsString(
            'site/gallery/photo.jpg',
            SiteImageService::url('photo.jpg') ?? '',
        );
    }

    public function test_it_resolves_jpeg_and_jpg_variants(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('site/gallery/team.jpg', 'binary');

        $this->assertSame(
            'site/gallery/team.jpg',
            SiteImageService::resolveExistingPath('site/gallery/team.jpeg'),
        );
    }

    public function test_it_finalizes_livewire_tmp_uploads_into_gallery(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('livewire-tmp/photo.jpeg', 'binary');

        $this->assertSame(
            'site/gallery/photo.jpeg',
            SiteImageService::finalizeUploadPath('livewire-tmp/photo.jpeg', 'site/gallery'),
        );

        Storage::disk('public')->assertExists('site/gallery/photo.jpeg');
    }

    public function test_it_keeps_remote_urls_unchanged(): void
    {
        $url = 'https://images.unsplash.com/photo-example';

        $this->assertSame($url, SiteImageService::url($url));
    }
}
