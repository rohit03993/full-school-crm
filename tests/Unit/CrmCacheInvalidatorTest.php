<?php

namespace Tests\Unit;

use App\Services\CrmDashboardService;
use App\Support\CrmCacheInvalidator;
use App\Support\CrmNavBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CrmCacheInvalidatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_after_admission_change_clears_dashboard_and_badge_caches(): void
    {
        Cache::put('crm.dashboard.stats', ['enquiries_today' => 99], 120);
        Cache::put('crm.nav.admissions_pending_action', 5, 120);

        CrmCacheInvalidator::afterAdmissionChange();

        $this->assertNull(Cache::get('crm.dashboard.stats'));
        $this->assertNull(Cache::get('crm.nav.admissions_pending_action'));
    }

    public function test_after_bulk_import_clears_dashboard_and_badge_caches(): void
    {
        Cache::put('crm.dashboard.stats', ['enquiries_today' => 1], 120);
        Cache::put('crm.nav.admissions_pending_action', 2, 120);

        CrmCacheInvalidator::afterBulkImport();

        $this->assertNull(Cache::get('crm.dashboard.stats'));
        $this->assertNull(Cache::get('crm.nav.admissions_pending_action'));
    }

    public function test_after_payment_clears_dashboard_cache(): void
    {
        app(CrmDashboardService::class)->stats();
        $this->assertNotNull(Cache::get('crm.dashboard.stats'));

        CrmCacheInvalidator::afterPayment();

        $this->assertNull(Cache::get('crm.dashboard.stats'));
    }

    public function test_after_admission_change_refreshes_badge_count(): void
    {
        CrmNavBadges::admissionsPendingAction();
        $cached = Cache::get('crm.nav.admissions_pending_action');
        $this->assertNotNull($cached);

        CrmCacheInvalidator::afterAdmissionChange();

        $this->assertNull(Cache::get('crm.nav.admissions_pending_action'));
    }
}
