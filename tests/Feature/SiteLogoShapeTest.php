<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Pages\ManageSiteContent;
use App\Models\Setting;
use App\Models\User;
use App\Support\InstituteSettings;
use App\Support\SiteContent;
use App\Support\SiteLogo;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SiteLogoShapeTest extends TestCase
{
    use RefreshDatabase;

    public function test_shape_defaults_to_wide_so_existing_logos_keep_rendering(): void
    {
        $this->assertSame(SiteLogo::SHAPE_WIDE, SiteLogo::normalizeShape(null));
        $this->assertSame(SiteLogo::SHAPE_WIDE, SiteLogo::normalizeShape(''));
        $this->assertSame(SiteLogo::SHAPE_WIDE, SiteLogo::normalizeShape('nonsense'));
        $this->assertFalse(SiteLogo::isSquare(null));
    }

    public function test_square_shape_is_recognised(): void
    {
        $this->assertSame(SiteLogo::SHAPE_SQUARE, SiteLogo::normalizeShape('square'));
        $this->assertSame(SiteLogo::SHAPE_SQUARE, SiteLogo::normalizeShape(' SQUARE '));
        $this->assertTrue(SiteLogo::isSquare('square'));
    }

    public function test_square_logo_gets_a_taller_panel_logo_than_a_wordmark(): void
    {
        Setting::setValue('site.logo_shape', SiteLogo::SHAPE_SQUARE, 'general');
        $this->assertSame(SiteLogo::SHAPE_SQUARE, InstituteSettings::logoShape());
        $square = InstituteSettings::panelLogoHeight();

        Setting::setValue('site.logo_shape', SiteLogo::SHAPE_WIDE, 'general');
        $wide = InstituteSettings::panelLogoHeight();

        $this->assertNotSame($wide, $square);
        $this->assertSame(SiteLogo::panelLogoHeight(SiteLogo::SHAPE_SQUARE), $square);
    }

    public function test_site_content_exposes_the_shape_to_public_views(): void
    {
        Setting::setValue('site.logo_shape', SiteLogo::SHAPE_SQUARE, 'general');
        SiteContent::clearCache();

        $this->assertSame(SiteLogo::SHAPE_SQUARE, SiteContent::institute()['logo_shape']);
    }

    public function test_public_header_shows_institute_name_beside_a_square_logo(): void
    {
        Setting::setValue('site.name', 'B.D.M. Kanya Degree College', 'general');
        Setting::setValue('site.logo', 'site/logo/crest.png', 'general');
        Setting::setValue('site.logo_shape', SiteLogo::SHAPE_SQUARE, 'general');
        SiteContent::clearCache();
        InstituteSettings::clearCache();

        $html = view('components.public.header', [
            'institute' => SiteContent::institute(),
        ])->render();

        // A crest carries no wordmark, so the name has to appear as text.
        $this->assertStringContainsString('B.D.M. Kanya Degree College', $html);
        // The wide banner frame must not squeeze the crest.
        $this->assertStringNotContainsString('aspect-ratio: '.SiteLogo::ASPECT_WIDTH, $html);
    }

    public function test_square_logo_can_hide_the_name_when_the_logo_already_has_one(): void
    {
        Setting::setValue('site.name', 'Motion Education', 'general');
        Setting::setValue('site.tagline', 'School & Coaching Management', 'general');
        Setting::setValue('site.logo', 'site/logo/wordmark.png', 'general');
        Setting::setValue('site.logo_shape', SiteLogo::SHAPE_SQUARE, 'general');
        Setting::setValue('site.logo_show_name', '0', 'general');
        SiteContent::clearCache();
        InstituteSettings::clearCache();

        $this->assertFalse(SiteContent::institute()['logo_shows_name']);

        $html = view('components.public.header', [
            'institute' => SiteContent::institute(),
        ])->render();

        // The logo already reads "Motion Education", so no duplicate text beside it.
        $this->assertStringNotContainsString('School &amp; Coaching Management', $html);
        // Logo-only mode gets the full header width while object-contain prevents
        // a circular or unusually shaped logo from being cropped.
        $this->assertStringContainsString('width: min(100%, '.SiteLogo::DISPLAY_MAX_WIDTH.'px)', $html);
        $this->assertStringContainsString('max-w-full object-contain', $html);
    }

    public function test_name_is_never_printed_beside_a_wide_logo(): void
    {
        // A wide banner already fills the strip, so the toggle must not apply.
        $this->assertFalse(SiteLogo::showsName(SiteLogo::SHAPE_WIDE, true));
        $this->assertTrue(SiteLogo::showsName(SiteLogo::SHAPE_SQUARE, null));
        $this->assertTrue(SiteLogo::showsName(SiteLogo::SHAPE_SQUARE, '1'));
        $this->assertFalse(SiteLogo::showsName(SiteLogo::SHAPE_SQUARE, '0'));
    }

    public function test_public_header_keeps_the_wide_frame_for_a_wordmark_logo(): void
    {
        Setting::setValue('site.logo', 'site/logo/wordmark.png', 'general');
        Setting::setValue('site.logo_shape', SiteLogo::SHAPE_WIDE, 'general');
        SiteContent::clearCache();
        InstituteSettings::clearCache();

        $html = view('components.public.header', [
            'institute' => SiteContent::institute(),
        ])->render();

        $this->assertStringContainsString('aspect-ratio: '.SiteLogo::ASPECT_WIDTH, $html);
    }

    public function test_admin_can_pick_and_save_the_logo_shape(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        Setting::setValue('site.name', 'Test Institute', 'general');
        Setting::setValue('crm.onboarding_completed', '1', 'crm');

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ManageSiteContent::class)
            ->assertSuccessful()
            ->assertFormFieldExists('logo_shape')
            ->fillForm([
                'logo_shape' => SiteLogo::SHAPE_SQUARE,
                'logo_show_name' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(SiteLogo::SHAPE_SQUARE, InstituteSettings::logoShape());

        SiteContent::clearCache();
        $this->assertFalse(SiteContent::institute()['logo_shows_name']);
    }
}
