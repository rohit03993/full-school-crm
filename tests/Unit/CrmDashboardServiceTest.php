<?php

namespace Tests\Unit;

use App\Enums\AdmissionStatus;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\StudentStatus;
use App\Models\Admission;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Student;
use App\Services\CrmDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_count_today_leads_and_pending_admissions(): void
    {
        $student = Student::query()->create([
            'name' => 'Lead',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876543210',
            'status' => StudentStatus::Enquiry,
        ]);

        $course = Course::query()->create([
            'name' => 'Diploma',
            'code' => 'DIP-DASH',
            'programme_category' => 'coaching',
            'duration' => 6,
            'duration_type' => 'months',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-2026-000200',
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'CRM-ADM-2026-000200',
            'status' => AdmissionStatus::VerificationPending,
        ]);

        $stats = app(CrmDashboardService::class)->stats();

        $this->assertSame(1, $stats['today_enquiries']);
        $this->assertSame(1, $stats['walk_in_today']);
        $this->assertSame(1, $stats['pending_admissions']);
        $this->assertSame(1, $stats['total_enquiries']);
    }

    public function test_batch_overview_includes_active_batches_without_end_date(): void
    {
        $course = Course::query()->create([
            'name' => 'IIT JEE',
            'code' => 'JEE-DASH',
            'programme_category' => 'coaching',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 100000,
            'status' => CourseStatus::Active,
        ]);

        Batch::query()->create([
            'name' => 'JEE Target Batch',
            'course_id' => $course->id,
            'start_date' => '2026-06-01',
            'end_date' => null,
            'status' => BatchStatus::Active,
        ]);

        Batch::query()->create([
            'name' => 'NEET Target Batch',
            'course_id' => $course->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);

        Batch::query()->create([
            'name' => 'Old Completed Batch',
            'course_id' => $course->id,
            'start_date' => '2025-06-01',
            'end_date' => '2025-12-31',
            'status' => BatchStatus::Completed,
        ]);

        CrmDashboardService::flushAllCaches();

        $overview = app(CrmDashboardService::class)->batchOverview();

        $this->assertCount(2, $overview['rows']);

        $labels = collect($overview['rows'])->pluck('label')->implode(' | ');
        $this->assertStringContainsString('JEE Target Batch', $labels);
        $this->assertStringContainsString('NEET Target Batch', $labels);
    }
}
