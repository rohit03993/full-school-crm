<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\ReportType;
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
use App\Services\ReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_absent_sheet_lists_students_without_present(): void
    {
        $this->travelTo('2026-07-10 12:00:00'); // Friday

        $seed = $this->seedBatchWithTwoStudents();
        $presentStudent = $seed['student_present'];
        $absentStudent = $seed['student_absent'];
        $batch = $seed['batch'];
        $staff = $seed['staff'];

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $presentStudent->id,
            'attendance_date' => '2026-07-10',
            'status' => AttendanceStatus::Present,
            'marked_by_user_id' => $staff->id,
        ]);

        $report = app(ReportService::class)->generate(ReportType::DailyAbsentSheet, [
            'date_from' => '2026-07-10',
            'date_to' => '2026-07-10',
            'batch_id' => $batch->id,
        ]);

        $names = collect($report['rows'])->pluck(2)->all();

        $this->assertContains($absentStudent->name, $names);
        $this->assertNotContains($presentStudent->name, $names);
    }

    public function test_monthly_and_low_attendance_reports(): void
    {
        $this->travelTo('2026-07-11 12:00:00'); // Saturday

        $seed = $this->seedBatchWithTwoStudents();
        $student = $seed['student_present'];
        $batch = $seed['batch'];
        $staff = $seed['staff'];

        // Only one present day in Jul 1–11 working days → low %
        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'attendance_date' => '2026-07-11',
            'status' => AttendanceStatus::Present,
            'marked_by_user_id' => $staff->id,
        ]);

        $monthly = app(ReportService::class)->generate(ReportType::MonthlyStudentAttendance, [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-11',
            'batch_id' => $batch->id,
            'student_id' => $student->id,
        ]);

        $this->assertNotEmpty($monthly['rows']);
        $this->assertSame($student->name, $monthly['rows'][0][0]);

        $low = app(ReportService::class)->generate(ReportType::LowAttendanceAlert, [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-11',
            'batch_id' => $batch->id,
            'max_percentage' => 75,
        ]);

        $this->assertTrue(
            collect($low['rows'])->contains(fn (array $row): bool => $row[0] === $student->name)
        );
    }

    /**
     * @return array{present: Student, absent: Student, batch: Batch, staff: User}
     */
    private function seedBatchWithTwoStudents(): array
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
            'name' => 'JEE',
            'code' => 'JEE-R',
            'programme_category' => 'coaching',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        $batch = Batch::query()->create([
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'name' => 'Batch A',
            'status' => BatchStatus::Active,
        ]);

        return [
            'student_present' => $this->makeEnrolled($course, $session, $batch, 'Present Kid', '9000000001', 'ROLL-P', $staff),
            'student_absent' => $this->makeEnrolled($course, $session, $batch, 'Absent Kid', '9000000002', 'ROLL-A', $staff),
            'batch' => $batch,
            'staff' => $staff,
        ];
    }

    private function makeEnrolled(
        Course $course,
        AcademicSession $session,
        Batch $batch,
        string $name,
        string $mobile,
        string $roll,
        User $staff,
    ): Student {
        $student = Student::query()->create([
            'name' => $name,
            'mobile' => $mobile,
            'gender' => Gender::Male,
            'status' => StudentStatus::Enrolled,
            'lead_source' => LeadSource::WalkIn,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'enquiry_number' => 'ENQ-'.$roll,
            'lead_source' => LeadSource::WalkIn,
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-'.$roll,
            'status' => \App\Enums\AdmissionStatus::Approved,
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
            'assigned_at' => '2026-07-01 08:00:00',
            'assigned_by_user_id' => $staff->id,
        ]);

        return $student;
    }
}
