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
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\Punch\PunchAttendanceProcessor;
use App\Services\Punch\PunchAttendanceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PunchAttendanceSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_punch_marks_present_on_active_batch(): void
    {
        [$student, $batch] = $this->createEnrolledStudent('ROLL-101');

        app(PunchAttendanceSyncService::class)->syncFromPunch(
            $student,
            '2026-06-20',
            'IN',
            '09:15:00',
        );

        $attendance = Attendance::query()->first();

        $this->assertNotNull($attendance);
        $this->assertSame(AttendanceStatus::Present, $attendance->status);
        $this->assertSame($batch->id, $attendance->batch_id);
        $this->assertSame('biometric', $attendance->punch_source);
        $this->assertNotNull($attendance->checked_in_at);
    }

    public function test_processor_handles_machine_punch_row(): void
    {
        if (! Schema::hasTable('punch_logs')) {
            $this->createPunchLogsTable();
        }

        $this->createEnrolledStudent('ROLL-202');

        DB::table('punch_logs')->insert([
            'employee_id' => 'ROLL-202',
            'punch_date' => '2026-06-20',
            'punch_time' => '10:00:00',
            'device_name' => 'Gate-1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Setting::setValue('attendance.last_processed_punch_log_id', '0', 'attendance');
        Setting::flushValueCache();

        app(PunchAttendanceProcessor::class)->processPending();

        $this->assertSame(AttendanceStatus::Present, Attendance::query()->first()?->status);
    }

    /**
     * @return array{0: Student, 1: Batch}
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
            'code' => 'PUNCH-10',
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
            'name' => 'Punch Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876543210',
            'status' => StudentStatus::Enrolled,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-'.substr($roll, -3),
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'CRM-ADM-'.substr($roll, -3),
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

        return [$student->fresh(['activeBatchStudent', 'activeEnrollment']), $batch];
    }

    private function createPunchLogsTable(): void
    {
        Schema::create('punch_logs', function ($table) {
            $table->id();
            $table->string('employee_id', 64);
            $table->date('punch_date');
            $table->time('punch_time');
            $table->string('device_name')->nullable();
            $table->timestamps();
        });
    }
}
