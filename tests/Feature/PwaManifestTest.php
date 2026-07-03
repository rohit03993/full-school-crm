<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PwaManifestTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_manifest_is_served(): void
    {
        $this->get(route('pwa.manifest', ['context' => 'public']))
            ->assertOk()
            ->assertHeader('content-type', 'application/manifest+json')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('start_url', '/')
            ->assertJsonPath('icons.0.sizes', '192x192');
    }

    public function test_portal_and_admin_manifests_use_scoped_start_urls(): void
    {
        $this->get(route('pwa.manifest', ['context' => 'portal']))
            ->assertOk()
            ->assertJsonPath('start_url', '/portal')
            ->assertJsonPath('scope', '/portal/');

        $this->get(route('pwa.manifest', ['context' => 'admin']))
            ->assertOk()
            ->assertJsonPath('start_url', '/admin')
            ->assertJsonPath('scope', '/admin/');
    }

    public function test_pwa_icons_are_available(): void
    {
        $this->get(route('pwa.icon', ['size' => 192]))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');

        $this->get(route('pwa.icon', ['size' => 512]))
            ->assertOk()
            ->assertHeader('content-type', 'image/png');
    }

    public function test_homepage_links_to_manifest(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('pwa.manifest', ['context' => 'public']), false);
    }

    public function test_service_worker_file_exists(): void
    {
        $this->assertFileExists(public_path('sw.js'));
        $this->assertStringContainsString('school-crm-pwa-v1', (string) file_get_contents(public_path('sw.js')));
    }
}
