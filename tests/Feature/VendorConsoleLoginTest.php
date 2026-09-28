<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\MetaWhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class VendorConsoleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_vendor_console_keeps_password_login_when_staff_must_use_otp(): void
    {
        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.otp_template_name', 'login_otp', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.otp_only_login', '1', 'meta_whatsapp');
        Setting::flushValueCache();

        $meta = Mockery::mock(MetaWhatsAppService::class);
        $meta->shouldReceive('isConfigured')->andReturn(true);
        $this->app->instance(MetaWhatsAppService::class, $meta);

        $this->get('/admin/login')->assertRedirect(route('staff.otp-login'));

        $this->get('/_vendor-console/login')
            ->assertOk()
            ->assertSee('Vendor sign in with mobile and password.', false)
            ->assertSee('Password', false);
    }
}
