<?php

namespace Tests\Feature;

use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppTemplateCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class WhatsAppTemplateCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_meta_mode_only_lists_templates_synced_on_meta(): void
    {
        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token-long-enough'), 'meta_whatsapp');

        WhatsAppTemplate::query()->create([
            'name' => 'old_pal_only',
            'param_count' => 0,
            'is_active' => true,
        ]);

        WhatsAppTemplate::query()->create([
            'name' => 'first_try',
            'param_count' => 1,
            'is_active' => true,
        ]);

        MetaWhatsAppTemplate::query()->create([
            'name' => 'first_try',
            'language' => 'en_US',
            'status' => 'APPROVED',
            'param_count' => 1,
            'is_active' => true,
            'synced_at' => now(),
        ]);

        $catalog = app(WhatsAppTemplateCatalog::class);

        $this->assertSame(['first_try'], $catalog->selectableTemplates()->pluck('name')->all());
        $this->assertSame(['old_pal_only'], $catalog->orphanedPalTemplateNames());
    }
}
