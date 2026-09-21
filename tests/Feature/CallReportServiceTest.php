<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\CallStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrolledCallPurpose;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\VisitStatus;
use App\Filament\Pages\CallReportPage;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Services\CallLogService;
use App\Services\CallReportService;
use App\Services\EnquiryService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_table_type_uses_first_ever_call_from_paginated_results(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createLeadStudent($staff);

        $first = app(CallLogService::class)->log($student, $staff, [
            'call_connected' => false,
            'call_status' => CallStatus::NoAnswer->value,
        ]);

        $second = app(CallLogService::class)->log($student->fresh(), $staff, [
            'call_connected' => true,
            'who_answered' => 'father',
            'visit_status' => VisitStatus::Interested->value,
            'call_notes' => 'Follow-up call connected with parent.',
            'next_followup_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ]);

        $report = app(CallReportService::class);
        $filters = $report->normalizeFilters([], $staff);
        $page = $report->calls($filters, $staff);
        $firstCallIds = $report->firstCallIdsFor($page);

        $this->assertSame((int) $first->id, (int) ($firstCallIds[$student->id] ?? 0));
        $this->assertTrue((int) ($firstCallIds[$student->id] ?? 0) === (int) $first->id);
        $this->assertFalse((int) ($firstCallIds[$student->id] ?? 0) === (int) $second->id);
        $this->assertSame(1, $report->summary($filters, $staff)['new_calls']);
        $this->assertSame(1, $report->summary($filters, $staff)['followup_calls']);
    }

    public function test_purpose_filter_limits_results_and_summary(): void
    {
        $staff = $this->createStaffUser();
        $enrolled = $this->createEnrolledStudent($staff);
        $lead = $this->createLeadStudent($staff);

        app(CallLogService::class)->logForEnrolledStudent($enrolled, $staff, [
            'call_connected' => true,
            'who_answered' => 'father',
            'call_purpose' => EnrolledCallPurpose::Attendance->value,
            'call_notes' => 'Asked about missing attendance yesterday.',
        ]);

        app(CallLogService::class)->logForEnrolledStudent($enrolled->fresh(['activeEnrollment']), $staff, [
            'call_connected' => true,
            'who_answered' => 'student',
            'call_purpose' => EnrolledCallPurpose::FeeQuery->value,
            'call_notes' => 'Discussed next fee installment due date.',
        ]);

        app(CallLogService::class)->log($lead, $staff, [
            'call_connected' => false,
            'call_status' => CallStatus::NoAnswer->value,
            'call_notes' => 'Lead call with no purpose.',
        ]);

        $report = app(CallReportService::class);
        $baseFilters = $report->normalizeFilters([], $staff);

        $this->assertSame(3, $report->summary($baseFilters, $staff)['total']);

        $attendanceFilters = [...$baseFilters, 'purpose' => EnrolledCallPurpose::Attendance->value];
        $attendance = $report->calls($attendanceFilters, $staff);
        $this->assertSame(1, $report->summary($attendanceFilters, $staff)['total']);
        $this->assertSame(1, $attendance->total());
        $this->assertSame(EnrolledCallPurpose::Attendance, $attendance->first()->call_purpose);

        $feeFilters = [...$baseFilters, 'purpose' => EnrolledCallPurpose::FeeQuery->value];
        $this->assertSame(1, $report->summary($feeFilters, $staff)['total']);

        $ignored = $report->normalizeFilters(['purpose' => 'not-a-real-purpose'], $staff);
        $this->assertNull($ignored['purpose']);
        $this->assertSame(3, $report->summary($ignored, $staff)['total']);
    }

    public function test_call_report_page_filters_by_purpose(): void
    {
        Role::findOrCreate(RoleName::SuperAdmin->value);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $student = $this->createEnrolledStudent($admin);
        app(CallLogService::class)->logForEnrolledStudent($student, $admin, [
            'call_connected' => true,
            'who_answered' => 'father',
            'call_purpose' => EnrolledCallPurpose::Attendance->value,
            'call_notes' => 'Attendance follow-up.',
        ]);
        app(CallLogService::class)->logForEnrolledStudent($student->fresh(['activeEnrollment']), $admin, [
            'call_connected' => true,
            'who_answered' => 'mother',
            'call_purpose' => EnrolledCallPurpose::FeeQuery->value,
            'call_notes' => 'Fee query follow-up.',
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CallReportPage::class)
            ->assertSuccessful()
            ->assertSee('Purpose')
            ->assertSee('Attendance')
            ->assertSee('Fee query')
            ->set('purposeFilter', EnrolledCallPurpose::Attendance->value)
            ->assertSee('Attendance follow-up.')
            ->assertDontSee('Fee query follow-up.');
    }

    public function test_call_report_page_exports_filtered_csv(): void
    {
        Role::findOrCreate(RoleName::SuperAdmin->value);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $student = $this->createLeadStudent($admin);
        app(CallLogService::class)->log($student, $admin, [
            'call_connected' => true,
            'who_answered' => 'father',
            'visit_status' => VisitStatus::Interested->value,
            'call_notes' => 'Export me please',
            'next_followup_at' => now()->addDay()->format('Y-m-d H:i:s'),
        ]);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CallReportPage::class)
            ->assertSuccessful()
            ->assertSee('Export CSV')
            ->call('exportCsv')
            ->assertFileDownloaded();
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

    protected function createEnrolledStudent(User $staff): \App\Models\Student
    {
        $course = Course::query()->create([
            'name' => 'Class 12',
            'code' => 'C12-CR-'.uniqid(),
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 80000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Enrolled Report Student',
            'mobile' => '9000000402',
            'course_id' => $course->id,
            'meeting_with_user_id' => $staff->id,
            'visit_status' => VisitStatus::Interested->value,
        ], $staff, LeadSource::WalkIn);

        $student = $enquiry->student;

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-CR-'.$student->id,
            'course_fee' => 80000,
            'discount_amount' => 0,
            'net_fee' => 80000,
            'use_installment_plan' => false,
            'status' => AdmissionStatus::Approved,
            'approved_at' => now(),
            'submitted_at' => now(),
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'enrollment_number' => 'ENR-CR-'.$student->id,
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        $student->update(['status' => StudentStatus::Enrolled]);

        return $student->fresh(['activeEnrollment']);
    }
}
