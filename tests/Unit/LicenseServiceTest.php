<?php

namespace Tests\Unit;

use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Models\Setting;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LicenseServiceTest extends TestCase
{
    use RefreshDatabase;

    private LicenseService $license;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::query()->whereIn('key', [
            LicenseService::PAYLOAD_KEY,
            LicenseService::SIGNATURE_KEY,
        ])->delete();
        Setting::flushValueCache();

        $this->license = app(LicenseService::class);
    }

    public function test_save_creates_valid_signed_license(): void
    {
        $this->license->save([
            'plan' => LicensePlan::Starter->value,
            'features' => $this->license->featuresForPlan(LicensePlan::Starter),
            'expires_at' => now()->addMonths(6)->toDateString(),
            'client_name' => 'Demo School',
            'annual_price_inr' => 25000,
        ]);

        $this->assertTrue($this->license->isSignatureValid());
        $this->assertTrue($this->license->isActive());
        $this->assertTrue($this->license->hasFeature(LicenseFeature::Attendance));
        $this->assertFalse($this->license->hasFeature(LicenseFeature::Fees));
    }

    public function test_tampered_payload_invalidates_signature(): void
    {
        $this->license->save([
            'plan' => LicensePlan::Starter->value,
            'features' => $this->license->featuresForPlan(LicensePlan::Starter),
            'expires_at' => now()->addYear()->toDateString(),
        ]);

        $payload = Setting::getValue(LicenseService::PAYLOAD_KEY);
        $payload['features'][] = LicenseFeature::Fees->value;
        Setting::setValue(LicenseService::PAYLOAD_KEY, $payload, 'license');
        Setting::flushValueCache();

        $this->assertFalse($this->license->isSignatureValid());
        $this->assertFalse($this->license->hasFeature(LicenseFeature::Fees));
    }

    public function test_expired_license_disables_features(): void
    {
        $this->license->save([
            'plan' => LicensePlan::FullResults->value,
            'features' => LicenseFeature::values(),
            'expires_at' => now()->subDay()->toDateString(),
        ]);

        $this->assertTrue($this->license->isSignatureValid());
        $this->assertTrue($this->license->isExpired());
        $this->assertFalse($this->license->isActive());
        $this->assertSame([], $this->license->enabledFeatureKeys());
    }

    public function test_apply_plan_sets_preset_features(): void
    {
        $this->license->applyPlan(LicensePlan::AcademicPlus, now()->addYear());

        $this->assertSame(LicensePlan::AcademicPlus, $this->license->plan());
        $this->assertTrue($this->license->hasFeature(LicenseFeature::Fees));
        $this->assertFalse($this->license->hasFeature(LicenseFeature::Enquiries));
    }

    public function test_dashboard_summary_flags_warning_and_critical_levels(): void
    {
        config([
            'license.expiry_warning_days' => 30,
            'license.expiry_critical_days' => 7,
        ]);

        $this->license->save([
            'plan' => LicensePlan::FullResults->value,
            'features' => LicenseFeature::values(),
            'expires_at' => now()->addDays(20)->toDateString(),
        ]);

        $summary = $this->license->dashboardSummary();

        $this->assertTrue($summary['show_warning']);
        $this->assertSame('warning', $summary['level']);

        $this->license->save([
            'plan' => LicensePlan::FullResults->value,
            'features' => LicenseFeature::values(),
            'expires_at' => now()->addDays(3)->toDateString(),
        ]);

        $summary = $this->license->dashboardSummary();

        $this->assertTrue($summary['show_warning']);
        $this->assertSame('critical', $summary['level']);
    }
}
