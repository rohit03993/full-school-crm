<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\WhatsAppProviderResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class WhatsAppProviderResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_reports_meta_saved_but_routing_off(): void
    {
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-token-long-enough'), 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.enabled', '0', 'meta_whatsapp');

        $resolver = app(WhatsAppProviderResolver::class);

        $this->assertFalse($resolver->isConfigured());
        $this->assertStringContainsString('routing is off', $resolver->configurationError());

        $diagnostics = $resolver->diagnostics();
        $this->assertTrue($diagnostics['meta_configured']);
        $this->assertFalse($diagnostics['meta_enabled']);
        $this->assertNull($diagnostics['active_provider']);
    }
}
