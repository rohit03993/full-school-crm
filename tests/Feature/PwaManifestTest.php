<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\PwaManifestService;
use App\Support\InstituteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PwaManifestTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue('site.name', 'Motion Agra', 'general');
        Setting::setValue('crm.id_card_primary_color', '#1e40af', 'crm');
        InstituteSettings::clearCache();
    }

    public function test_public_manifest_is_served(): void
    {
        $this->get(route('pwa.manifest', ['context' => 'public']))
            ->assertOk()
            ->assertHeader('content-type', 'application/manifest+json')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('start_url', '/')
            ->assertJsonPath('name', 'Motion Agra')
            ->assertJsonPath('short_name', 'Motion')
            ->assertJsonPath('theme_color', '#1E40AF')
            ->assertJsonPath('icons.0.sizes', '192x192');
    }

    public function test_portal_and_admin_manifests_use_institute_names_and_short_labels(): void
    {
        $this->get(route('pwa.manifest', ['context' => 'portal']))
            ->assertOk()
            ->assertJsonPath('start_url', '/portal')
            ->assertJsonPath('scope', '/portal/')
            ->assertJsonPath('name', 'Motion Agra Portal')
            ->assertJsonPath('short_name', 'MA Portal');

        $this->get(route('pwa.manifest', ['context' => 'admin']))
            ->assertOk()
            ->assertJsonPath('start_url', '/admin')
            ->assertJsonPath('scope', '/admin/')
            ->assertJsonPath('name', 'Motion Agra Admin')
            ->assertJsonPath('short_name', 'Motion Admin');
    }

    public function test_long_brand_names_fall_back_to_initials_for_short_name(): void
    {
        Setting::setValue('site.name', 'International Coaching Academy', 'general');
        InstituteSettings::clearCache();

        $this->assertSame('ICA Admin', PwaManifestService::shortName('admin'));
        $this->assertSame('ICA Portal', PwaManifestService::shortName('portal'));
        $this->assertLessThanOrEqual(12, strlen(PwaManifestService::shortName('admin')));
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

    public function test_favicon_is_preferred_over_logo_for_every_app_icon_size(): void
    {
        $disk = Storage::disk('public');
        $disk->put('site/favicon/mark.png', $this->tinyPng());
        $disk->put('site/logo/wide.png', $this->tinyPng());

        Setting::setValue('site.favicon', 'site/favicon/mark.png', 'general');
        Setting::setValue('site.logo', 'site/logo/wide.png', 'general');

        $this->assertSame('site/favicon/mark.png', PwaManifestService::iconSourcePath(192));
        $this->assertSame('site/favicon/mark.png', PwaManifestService::iconSourcePath(512));
    }

    public function test_logo_is_used_for_app_icons_when_favicon_is_missing(): void
    {
        $disk = Storage::disk('public');
        $disk->put('site/logo/wide.png', $this->tinyPng());

        Setting::setValue('site.logo', 'site/logo/wide.png', 'general');
        Setting::setValue('site.favicon', '', 'general');

        $this->assertSame('site/logo/wide.png', PwaManifestService::iconSourcePath(512));
    }

    /**
     * Minimal valid 1×1 PNG so GD / Storage tests do not need a real image fixture.
     */
    protected function tinyPng(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==',
            true,
        ) ?: '';
    }

    public function test_homepage_links_to_manifest(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('/pwa/manifest/public', false)
            ->assertSee('apple-mobile-web-app-title', false);
    }

    public function test_service_worker_and_offline_page_exist(): void
    {
        $this->assertFileExists(public_path('sw.js'));
        $this->assertFileExists(public_path('offline.html'));

        $sw = (string) file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString('school-crm-pwa-v2', $sw);
        $this->assertStringContainsString('/offline.html', $sw);

        $offline = (string) file_get_contents(public_path('offline.html'));
        $this->assertStringContainsString("You're offline", $offline);
    }

    public function test_admin_panel_wires_pwa_head_and_install_prompt(): void
    {
        $this->assertStringContainsString(
            'pwa-head',
            file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php')),
        );
        $this->assertStringContainsString(
            'install-prompt',
            file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php')),
        );
    }
}
