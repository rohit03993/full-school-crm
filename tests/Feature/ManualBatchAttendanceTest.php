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
use App\Services\Punch\ManualBatchAttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManualBatchAttendanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_in_uses_punch_flow(): void
    {
        $this->travelTo('2026-06-20 08:30:00');

        [$student, $batch, $staff] = $this->createEnrolledStudent('ROLL-IN');

        app(ManualBatchAttendanceService::class)->save(
            $batch,
            '2026-06-20',
            [$student->id => AttendanceStatus::Present->value],
            $staff,
        );

        $attendance = Attendance::query()->first();

        $this->assertNotNull($attendance);
        $this->assertSame(AttendanceStatus::Present, $attendance->status);
        $this->assertSame('manual', $attendance->punch_source);
        $this->assertSame('08:30:00', $attendance->checked_in_at?->format('H:i:s'));
    }

    public function test_manual_in_method_returns_result(): void
    {
        $this->travelTo('2026-06-20 08:30:00');

        [$student, , $staff] = $this->createEnrolledStudent('ROLL-IN2');

        $result = app(ManualBatchAttendanceService::class)->manualIn($student, '2026-06-20', $staff);

        $this->assertTrue($result['ok']);
        $this->assertArrayHasKey('whatsapp', $result);
        $this->assertIsArray($result['whatsapp']);
    }

    public function test_manual_out_records_checkout(): void
    {
        $this->travelTo('2026-06-20 16:00:00');

        [$student, $batch, $staff] = $this->createEnrolledStudent('ROLL-OUT');

        $service = app(ManualBatchAttendanceService::class);
        $service->save($batch, '2026-06-20', [$student->id => AttendanceStatus::Present->value], $staff);
        $result = $service->manualOut($student->fresh(), '2026-06-20', $staff);

        $this->assertTrue($result['ok']);

        $attendance = Attendance::query()->first();

        $this->assertNotNull($attendance->checked_out_at);
        $this->assertSame('16:00:00', $attendance->checked_out_at?->format('H:i:s'));
    }

    public function test_absent_uses_roll_call_source(): void
    {
        $this->travelTo('2026-06-20 10:00:00');

        [$student, $batch, $staff] = $this->createEnrolledStudent('ROLL-ABS');

        app(ManualBatchAttendanceService::class)->save(
            $batch,
            '2026-06-20',
            [$student->id => AttendanceStatus::Absent->value],
            $staff,
        );

        $attendance = Attendance::query()->first();

        $this->assertSame(AttendanceStatus::Absent, $attendance->status);
        $this->assertSame('roll_call', $attendance->punch_source);
        $this->assertNull($attendance->checked_in_at);
    }

    public function test_manual_save_rejects_backdated_date(): void
    {
        $this->travelTo('2026-06-20 10:00:00');

        [$student, $batch, $staff] = $this->createEnrolledStudent('ROLL-BACK');

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(ManualBatchAttendanceService::class)->save(
            $batch,
            '2026-06-19',
            [$student->id => AttendanceStatus::Absent->value],
            $staff,
        );
    }

    public function test_manual_in_rejects_backdated_date(): void
    {
        $this->travelTo('2026-06-20 10:00:00');

        [$student, , $staff] = $this->createEnrolledStudent('ROLL-BACK-IN');

        $result = app(ManualBatchAttendanceService::class)->manualIn($student, '2026-06-19', $staff);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('today', strtolower($result['message']));
    }

    /**
     * @return array{0: Student, 1: Batch, 2: User}
     */
    private function createEnrolledStudent(string $roll): array
    {
        $staff = User::factory()->create(['is_active' => true]);

        $session = AcademicSession::query()->create([
            'name' => '2026-27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'MAN-10',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        $batch = Batch::query()->create([
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'name' => '10-A',
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);

        $student = Student::query()->create([
            'name' => 'Manual Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876501234',
            'status' => StudentStatus::Enrolled,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-M1',
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'CRM-ADM-M1',
            'status' => AdmissionStatus::Approved,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'enrollment_number' => $roll,
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $staff->id,
        ]);

        return [$student->fresh(), $batch, $staff];
    }
}
