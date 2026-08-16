<?php

namespace Tests\Unit;

use App\Enums\CourseStatus;
use App\Enums\LeadSource;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Student;
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
        $before = app(CrmDashboardService::class)->stats();
        $this->assertSame(0, $before['total_enquiries']);

        $this->createEnquiry();
        Cache::put('crm.nav.admissions_pending_action', 5, 120);

        CrmCacheInvalidator::afterAdmissionChange();

        $this->assertSame(1, app(CrmDashboardService::class)->stats()['total_enquiries']);
        $this->assertNull(Cache::get('crm.nav.admissions_pending_action'));
    }

    public function test_after_bulk_import_clears_dashboard_and_badge_caches(): void
    {
        app(CrmDashboardService::class)->stats();
        $this->createEnquiry();
        Cache::put('crm.nav.admissions_pending_action', 2, 120);

        CrmCacheInvalidator::afterBulkImport();

        $this->assertSame(1, app(CrmDashboardService::class)->stats()['total_enquiries']);
        $this->assertNull(Cache::get('crm.nav.admissions_pending_action'));
    }

    public function test_after_payment_clears_dashboard_cache(): void
    {
        $service = app(CrmDashboardService::class);
        $service->stats();

        $this->createEnquiry();

        // Still served from cache until the invalidator bumps the version.
        $this->assertSame(0, $service->stats()['total_enquiries']);

        CrmCacheInvalidator::afterPayment();

        $this->assertSame(1, $service->stats()['total_enquiries']);
    }

    public function test_after_admission_change_refreshes_badge_count(): void
    {
        CrmNavBadges::admissionsPendingAction();
        $cached = Cache::get('crm.nav.admissions_pending_action');
        $this->assertNotNull($cached);

        CrmCacheInvalidator::afterAdmissionChange();

        $this->assertNull(Cache::get('crm.nav.admissions_pending_action'));
    }

    protected function createEnquiry(): Enquiry
    {
        $student = Student::query()->create([
            'name' => 'Cache Lead',
            'mobile' => '9800000001',
            'status' => StudentStatus::Enquiry,
        ]);

        $course = Course::query()->create([
            'name' => 'Cache Course',
            'code' => 'CACHE-1',
            'programme_category' => 'coaching',
            'duration' => 6,
            'duration_type' => 'months',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        return Enquiry::query()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'enquiry_number' => 'CRM-ENQ-2026-000900',
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);
    }
}
