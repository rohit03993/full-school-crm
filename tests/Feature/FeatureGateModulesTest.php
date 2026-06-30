<?php

namespace Tests\Feature;

use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Models\Setting;
use App\Services\LicenseService;
use App\Support\FeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeatureGateModulesTest extends TestCase
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

    public function test_custom_license_toggles_features_independently(): void
    {
        $this->license->save([
            'plan' => LicensePlan::Custom->value,
            'features' => [LicenseFeature::Attendance->value, LicenseFeature::Marks->value],
            'expires_at' => now()->addYear()->toDateString(),
        ]);

        $this->assertTrue(FeatureGate::enabled(LicenseFeature::Attendance));
        $this->assertTrue(FeatureGate::enabled(LicenseFeature::Marks));
        $this->assertFalse(FeatureGate::enabled(LicenseFeature::Fees));
        $this->assertFalse(FeatureGate::enabled(LicenseFeature::Enquiries));
    }

    public function test_disabled_enquiries_module_blocks_resource_access_check(): void
    {
        $this->license->save([
            'plan' => LicensePlan::Custom->value,
            'features' => [LicenseFeature::Attendance->value],
            'expires_at' => now()->addYear()->toDateString(),
        ]);

        $this->assertFalse(EnquiryResource::canAccess());
    }
}
