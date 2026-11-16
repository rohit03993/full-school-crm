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

    public function test_attendance_by_student_report_lists_selected_student_rows(): void
    {
        $this->travelTo('2026-07-11 12:00:00');

        $seed = $this->seedBatchWithTwoStudents();
        $student = $seed['student_present'];
        $batch = $seed['batch'];
        $staff = $seed['staff'];

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'attendance_date' => '2026-07-11',
            'status' => AttendanceStatus::Present,
            'marked_by_user_id' => $staff->id,
        ]);

        $withoutStudent = app(ReportService::class)->generate(ReportType::AttendanceByStudent, [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-11',
        ]);

        $this->assertSame('Attendance by student — select a student', $withoutStudent['title']);
        $this->assertSame([], $withoutStudent['rows']);

        $report = app(ReportService::class)->generate(ReportType::AttendanceByStudent, [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-11',
            'student_id' => $student->id,
        ]);

        $this->assertStringContainsString($student->name, $report['title']);
        $this->assertCount(1, $report['rows']);
        $this->assertSame('Batch A', $report['rows'][0][1]);
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

    public function test_class_and_student_attendance_report_sorts_lowest_first_and_counts_a_student_once(): void
    {
        $this->travelTo('2026-07-11 12:00:00');

        $seed = $this->seedBatchWithTwoStudents();
        $presentStudent = $seed['student_present'];
        $absentStudent = $seed['student_absent'];
        $batch = $seed['batch'];
        $staff = $seed['staff'];
        $course = $batch->course;

        $secondBatch = Batch::query()->create([
            'course_id' => $course->id,
            'academic_session_id' => $batch->academic_session_id,
            'name' => 'Batch B',
            'status' => BatchStatus::Active,
        ]);

        BatchStudent::query()->create([
            'batch_id' => $secondBatch->id,
            'student_id' => $absentStudent->id,
            'is_active' => true,
            'assigned_at' => '2026-07-02 08:00:00',
            'assigned_by_user_id' => $staff->id,
        ]);

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $presentStudent->id,
            'attendance_date' => '2026-07-09',
            'status' => AttendanceStatus::Leave,
            'marked_by_user_id' => $staff->id,
        ]);

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $presentStudent->id,
            'attendance_date' => '2026-07-10',
            'status' => AttendanceStatus::Present,
            'marked_by_user_id' => $staff->id,
        ]);

        Attendance::query()->create([
            'batch_id' => $secondBatch->id,
            'student_id' => $absentStudent->id,
            'attendance_date' => '2026-07-10',
            'status' => AttendanceStatus::Leave,
            'marked_by_user_id' => $staff->id,
        ]);

        $filters = [
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-11',
            'course_id' => $course->id,
        ];

        $students = app(ReportService::class)->generate(ReportType::ClassStudentAttendance, [
            ...$filters,
            'attendance_view' => 'student',
        ]);

        $this->assertSame('Student attendance · 01 Jul 2026 – 11 Jul 2026', $students['title']);
        $this->assertCount(2, $students['rows']);
        $this->assertSame($absentStudent->name, $students['rows'][0][0]);
        $this->assertSame($presentStudent->name, $students['rows'][1][0]);
        $this->assertSame(9, $students['percent_column']);
        $this->assertGreaterThan(0, (int) $students['rows'][0][6]);
        $this->assertSame(0.0, (float) $students['rows'][0][9]);
        $this->assertSame(1, (int) $students['rows'][1][5]);
        $this->assertSame(11, (int) $students['rows'][1][8]);
        $this->assertGreaterThan(0, (int) $students['rows'][1][6]);
        $this->assertSame(
            round(((int) $students['rows'][1][5] / (int) $students['rows'][1][8]) * 100, 1),
            (float) $students['rows'][1][9],
        );

        $below = app(ReportService::class)->generate(ReportType::ClassStudentAttendance, [
            ...$filters,
            'attendance_view' => 'student',
            'max_percentage' => 5,
        ]);

        $this->assertCount(1, $below['rows']);
        $this->assertSame($absentStudent->name, $below['rows'][0][0]);

        $classes = app(ReportService::class)->generate(ReportType::ClassStudentAttendance, [
            ...$filters,
            'attendance_view' => 'class',
            'max_percentage' => 5,
        ]);

        $this->assertSame('Class attendance · 01 Jul 2026 – 11 Jul 2026', $classes['title']);
        $this->assertCount(1, $classes['rows']);
        $this->assertSame('JEE', $classes['rows'][0][0]);
        $this->assertSame(2, $classes['rows'][0][1]);
        $this->assertSame(6, $classes['percent_column']);

        $register = app(ReportService::class)->attendanceRegister($filters);
        $sunday = $register['dates'][4];

        $this->assertCount(11, $register['dates']);
        $this->assertSame('2026-07-05', $sunday['date']);
        $this->assertSame('Sun', $sunday['weekday']);
        $this->assertSame('Batch A', $register['sheets'][0]['batch_name']);
        $this->assertSame('P', $register['sheets'][0]['students'][0]['marks'][9]);
        $this->assertSame('L', $register['sheets'][0]['students'][0]['marks'][8]);
        $this->assertSame('A', $register['sheets'][0]['students'][0]['marks'][4]);
        $this->assertSame('Batch B', $register['sheets'][1]['batch_name']);
        $this->assertSame('', $register['sheets'][1]['students'][0]['marks'][0]);
        $this->assertSame('L', $register['sheets'][1]['students'][0]['marks'][9]);
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
