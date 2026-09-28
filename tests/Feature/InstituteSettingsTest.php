<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Enums\NumberSequenceType;
use App\Services\InstituteSettingsService;
use App\Services\SiteImageService;
use App\Support\InstituteSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstituteSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_receipt_footer_can_be_saved_from_admin_settings(): void
    {
        app(InstituteSettingsService::class)->save([
            'receipt_footer' => 'Custom receipt footer for demo.',
            'receipt_header' => 'Training Division',
            'receipt_logo' => null,
        ]);

        InstituteSettings::clearCache();

        $settings = InstituteSettings::forDocuments();

        $this->assertSame('Custom receipt footer for demo.', $settings['footer']);
        $this->assertSame('Training Division', $settings['receipt_header']);
        $this->assertSame('Training Division', Setting::getValue('crm.receipt_header'));
    }

    public function test_document_branding_falls_back_to_site_name(): void
    {
        Setting::setValue('site.name', 'Demo Institute', 'general');

        InstituteSettings::clearCache();

        $this->assertSame('Demo Institute', InstituteSettings::forDocuments()['name']);
    }

    public function test_number_prefix_falls_back_to_config(): void
    {
        config(['institute.number_prefix' => 'DEMO']);

        $this->assertSame('DEMO', InstituteSettings::numberPrefix());
        $this->assertSame('DEMO-ENQ', NumberSequenceType::Enquiry->prefix());
    }

    public function test_admin_can_replace_the_homework_link_picture(): void
    {
        Storage::fake(SiteImageService::DISK);
        Storage::disk(SiteImageService::DISK)->put('crm/branding/school-banner.png', 'banner');

        app(InstituteSettingsService::class)->save([
            'receipt_logo' => null,
            'homework_footer_image' => 'crm/branding/school-banner.png',
            'homework_footer_enabled' => true,
        ]);

        $url = InstituteSettings::homeworkFooterImageUrl();

        $this->assertNotNull($url);
        $this->assertStringContainsString('crm/branding/school-banner.png', $url);
    }

    public function test_admin_can_hide_the_homework_link_picture(): void
    {
        app(InstituteSettingsService::class)->save([
            'receipt_logo' => null,
            'homework_footer_image' => null,
            'homework_footer_enabled' => false,
        ]);

        $this->assertNull(InstituteSettings::homeworkFooterImageUrl());
    }
}
