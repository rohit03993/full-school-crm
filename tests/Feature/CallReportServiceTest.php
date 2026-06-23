<?php

namespace Tests\Feature;

use App\Enums\CallStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\VisitStatus;
use App\Models\User;
use App\Services\CallLogService;
use App\Services\CallReportService;
use App\Services\EnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CallReportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_splits_new_and_follow_up_calls(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createLeadStudent($staff);

        $report = app(CallReportService::class);
        $filters = $report->normalizeFilters([], $staff);

        app(CallLogService::class)->log($student, $staff, [
            'call_connected' => false,
            'call_status' => CallStatus::NoAnswer->value,
            'call_notes' => 'First attempt no answer.',
        ]);

        app(CallLogService::class)->log($student->fresh(), $staff, [
            'call_connected' => true,
            'who_answered' => 'father',
            'visit_status' => VisitStatus::Interested->value,
            'call_notes' => 'Second attempt connected successfully.',
            'next_followup_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ]);

        $summary = $report->summary($filters, $staff);

        $this->assertSame(2, $summary['total']);
        $this->assertSame(1, $summary['new_calls']);
        $this->assertSame(1, $summary['followup_calls']);
        $this->assertSame(1, $summary['connected']);
        $this->assertSame(1, $summary['not_connected']);
    }

    public function test_staff_only_sees_own_calls_in_report(): void
    {
        $staffA = $this->createStaffUser('Caller A');
        $staffB = $this->createStaffUser('Caller B');

        $student = $this->createLeadStudent($staffA);

        app(CallLogService::class)->log($student, $staffA, [
            'call_connected' => true,
            'who_answered' => 'father',
            'visit_status' => VisitStatus::Interested->value,
            'call_notes' => 'Staff A connected with the parent.',
            'next_followup_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ]);

        app(CallLogService::class)->log($student->fresh(), $staffB, [
            'call_connected' => false,
            'call_status' => CallStatus::Busy->value,
        ]);

        $report = app(CallReportService::class);
        $filters = $report->normalizeFilters([], $staffA);

        $this->assertSame(1, $report->summary($filters, $staffA)['total']);
        $this->assertSame(1, $report->calls($filters, $staffA)->total());
    }

    public function test_call_type_filter_limits_results(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createLeadStudent($staff);

        app(CallLogService::class)->log($student, $staff, [
            'call_connected' => false,
            'call_status' => CallStatus::NoAnswer->value,
        ]);

        app(CallLogService::class)->log($student->fresh(), $staff, [
            'call_connected' => true,
            'who_answered' => 'father',
            'visit_status' => VisitStatus::Interested->value,
            'call_notes' => 'Follow-up call connected with parent.',
            'next_followup_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ]);

        $report = app(CallReportService::class);
        $baseFilters = $report->normalizeFilters([], $staff);

        $newFilters = [...$baseFilters, 'call_type' => 'new'];
        $followupFilters = [...$baseFilters, 'call_type' => 'followup'];

        $this->assertSame(1, $report->summary($newFilters, $staff)['total']);
        $this->assertSame(1, $report->summary($followupFilters, $staff)['total']);
    }

    protected function createStaffUser(string $name = 'Staff User'): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['name' => $name, 'is_active' => true]);
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }

    protected function createLeadStudent(User $staff): \App\Models\Student
    {
        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Report Test Student',
            'mobile' => '9000000301',
            'discussion_summary' => 'Report test lead.',
            'visit_status' => VisitStatus::Interested->value,
            'meeting_with_user_id' => $staff->id,
        ], $staff, LeadSource::WalkIn);

        return $enquiry->student;
    }
}
