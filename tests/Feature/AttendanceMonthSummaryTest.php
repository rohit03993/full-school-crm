<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceMonthSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_month_summary_is_month_to_date_with_clear_label(): void
    {
        $this->travelTo('2026-09-09 12:00:00');

        $seed = $this->seedStudentInBatch('2026-09-04 08:00:00');
        $student = $seed['student'];
        $batch = $seed['batch'];
        $staff = $seed['staff'];

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'attendance_date' => '2026-09-08',
            'status' => AttendanceStatus::Present,
            'marked_by_user_id' => $staff->id,
        ]);

        $summary = app(AttendanceService::class)->summaryForStudentInMonth($student->fresh(), '2026-09');

        $this->assertNotNull($summary);
        $this->assertSame('month_to_date', $summary['scope']);
        // 4–9 Sep excluding Sunday 6 Sep = 5 working days
        $this->assertSame(5, $summary['expected_days']);
        $this->assertSame(1, $summary['present_days']);
        $this->assertSame(20.0, $summary['percentage']);
        $this->assertStringStartsWith('so far ', $summary['period_label']);
        $this->assertStringContainsString('04 Sep', $summary['period_label']);
        $this->assertStringContainsString('09 Sep 2026', $summary['period_label']);
    }

    public function test_past_month_summary_uses_full_calendar_month(): void
    {
        $this->travelTo('2026-09-09 12:00:00');

        $seed = $this->seedStudentInBatch('2026-08-01 08:00:00');
        $student = $seed['student'];
        $batch = $seed['batch'];
        $staff = $seed['staff'];

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'attendance_date' => '2026-08-03',
            'status' => AttendanceStatus::Present,
            'marked_by_user_id' => $staff->id,
        ]);

        $summary = app(AttendanceService::class)->summaryForStudentInMonth($student->fresh(), '2026-08');

        $this->assertNotNull($summary);
        $this->assertSame('calendar_month', $summary['scope']);
        // Aug 2026 = 31 days, 5 Sundays → 26 working days
        $this->assertSame(26, $summary['expected_days']);
        $this->assertSame(1, $summary['present_days']);
        $this->assertStringStartsWith('01 Aug', $summary['period_label']);
        $this->assertStringContainsString('31 Aug 2026', $summary['period_label']);
        $this->assertStringNotContainsString('so far', $summary['period_label']);
    }

    /**
     * @return array{student: Student, batch: Batch, staff: User}
     */
    protected function seedStudentInBatch(string $assignedAt): array
    {
        $staff = User::factory()->create(['is_active' => true]);

        $session = AcademicSession::query()->create([
            'name' => '2026-27',
            'code' => '2026-27-att',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Attendance Month Course',
            'code' => 'ATT-MO',
            'programme_category' => 'coaching',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        $batch = Batch::query()->create([
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'name' => 'Attendance Month Batch',
            'status' => BatchStatus::Active,
        ]);

        $student = Student::query()->create([
            'name' => 'Month Summary Student',
            'mobile' => '9811100001',
            'gender' => Gender::Male,
            'status' => StudentStatus::Enrolled,
            'lead_source' => LeadSource::WalkIn,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'enquiry_number' => 'ENQ-ATT-MO',
            'lead_source' => LeadSource::WalkIn,
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-ATT-MO',
            'status' => AdmissionStatus::Approved,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'enrollment_number' => 'ATT-MO-1',
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'is_active' => true,
            'assigned_at' => $assignedAt,
            'assigned_by_user_id' => $staff->id,
        ]);

        return [
            'student' => $student->fresh(),
            'batch' => $batch,
            'staff' => $staff,
        ];
    }
}
