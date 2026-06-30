<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\ReportType;
use App\Enums\RoleName;
use App\Models\Course;
use App\Models\User;
use App\Services\EnquiryService;
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_enquiries_report_includes_rows_in_date_range(): void
    {
        $staff = $this->createSuperAdmin();
        $course = $this->createCourse();

        app(EnquiryService::class)->create([
            'name' => 'Report Lead',
            'father_name' => 'Parent',
            'mobile' => '9811000091',
            'gender' => Gender::Male->value,
            'course_id' => $course->id,
        ], $staff, LeadSource::WalkIn);

        $report = app(ReportService::class)->generate(ReportType::Enquiries, [
            'date_from' => now()->subDay()->toDateString(),
            'date_to' => now()->addDay()->toDateString(),
        ]);

        $this->assertStringStartsWith('Enquiries', $report['title']);
        $this->assertNotEmpty($report['columns']);
        $this->assertCount(1, $report['rows']);
        $this->assertStringContainsString('Report Lead', (string) $report['rows'][0][2]);
    }

    public function test_financial_summary_report_returns_expected_columns(): void
    {
        $report = app(ReportService::class)->generate(ReportType::FinancialSummary, [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
        ]);

        $this->assertStringStartsWith('Financial summary', $report['title']);
        $this->assertContains('Metric', $report['columns']);
        $this->assertNotEmpty($report['rows']);
    }

    public function test_pending_fees_report_lists_active_enrollments_with_balance(): void
    {
        $report = app(ReportService::class)->generate(ReportType::PendingFees, []);

        $this->assertSame('Pending fees', $report['title']);
        $this->assertContains('Student', $report['columns']);
    }

    protected function createSuperAdmin(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createCourse(): Course
    {
        return Course::query()->create([
            'name' => 'Report Test Course',
            'code' => 'RPT-101',
            'programme_category' => 'coaching',
            'duration' => 6,
            'duration_type' => 'months',
            'fee' => 25000,
            'status' => CourseStatus::Active,
        ]);
    }
}
