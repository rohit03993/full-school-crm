<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Pages\AttendanceHubPage;
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
use App\Services\AttendanceHubOverviewService;
use App\Support\AttendanceLeaveReasons;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceHubClassWiseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleName::SuperAdmin->value);
    }

    public function test_class_rows_absent_includes_unmarked_and_omits_unmarked_column(): void
    {
        $this->travelTo('2026-09-11 10:00:00');

        [$batch, $present, $leave, $explicitAbsent, $unmarked, $staff] = $this->seedClassWithFourStatuses();

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $present->id,
            'attendance_date' => '2026-09-11',
            'status' => AttendanceStatus::Present,
            'punch_source' => 'roll_call',
            'marked_by_user_id' => $staff->id,
        ]);
        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $leave->id,
            'attendance_date' => '2026-09-11',
            'status' => AttendanceStatus::Leave,
            'punch_source' => 'roll_call',
            'leave_reason' => 'Sick',
            'marked_by_user_id' => $staff->id,
        ]);
        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $explicitAbsent->id,
            'attendance_date' => '2026-09-11',
            'status' => AttendanceStatus::Absent,
            'punch_source' => 'roll_call',
            'marked_by_user_id' => $staff->id,
        ]);

        $overview = app(AttendanceHubOverviewService::class)->overview('2026-09-11');
        $row = collect($overview['class_rows'])->firstWhere('batch_id', $batch->id);

        $this->assertNotNull($row);
        $this->assertSame(4, $row['expected']);
        $this->assertSame(1, $row['present']);
        $this->assertSame(1, $row['leave']);
        $this->assertSame(2, $row['absent']);
        $this->assertArrayNotHasKey('unmarked', $row);

        $presentRoster = app(AttendanceHubOverviewService::class)
            ->classBucketRoster($batch->id, '2026-09-11', 'present');
        $absentRoster = app(AttendanceHubOverviewService::class)
            ->classBucketRoster($batch->id, '2026-09-11', 'absent');

        $this->assertSame([$present->id], collect($presentRoster['students'])->pluck('id')->all());
        $this->assertEqualsCanonicalizing(
            [$explicitAbsent->id, $unmarked->id],
            collect($absentRoster['students'])->pluck('id')->all(),
        );
        $this->assertTrue(collect($absentRoster['students'])->every(fn (array $s): bool => $s['can_mark'] === true));
    }

    public function test_hub_can_mark_leave_from_class_drill(): void
    {
        $this->travelTo('2026-09-11 10:00:00');

        [$batch] = $this->seedClassWithFourStatuses();
        $admin = $this->actingAsAdmin();

        $leaveStudent = Student::query()->create([
            'name' => 'Leave Target',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Female,
            'mobile' => '9876509999',
            'status' => StudentStatus::Enrolled,
        ]);
        $this->attachStudentToBatch($leaveStudent, $batch, $admin, 'HUB-LEAVE');

        Livewire::test(AttendanceHubPage::class)
            ->set('overviewDate', '2026-09-11')
            ->call('openClassDrill', $batch->id, 'absent')
            ->assertSet('classDrillBatchId', $batch->id)
            ->assertSet('classDrillBucket', 'absent')
            ->assertSee('Leave Target')
            ->call('startLeaveMark', $leaveStudent->id)
            ->set('leaveReasonTag', AttendanceLeaveReasons::tags()[0] ?? 'Personal work')
            ->set('leaveReasonCustom', '')
            ->call('confirmHubLeave')
            ->assertSet('leaveStudentId', null);

        $leaveRow = Attendance::query()
            ->where('student_id', $leaveStudent->id)
            ->whereDate('attendance_date', '2026-09-11')
            ->first();

        $this->assertNotNull($leaveRow);
        $this->assertSame(AttendanceStatus::Leave, $leaveRow->status);
    }

    public function test_hub_can_mark_present_via_manual_in_from_absent_drill(): void
    {
        $this->travelTo('2026-09-11 10:00:00');

        [$batch, , , , $unmarked] = $this->seedClassWithFourStatuses();
        $this->actingAsAdmin();

        Livewire::test(AttendanceHubPage::class)
            ->set('overviewDate', '2026-09-11')
            ->call('openClassDrill', $batch->id, 'absent')
            ->call('markHubPresent', $unmarked->id);

        $row = Attendance::query()
            ->where('student_id', $unmarked->id)
            ->whereDate('attendance_date', '2026-09-11')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(AttendanceStatus::Present, $row->status);
        $this->assertSame('manual', $row->punch_source);
        $this->assertNotNull($row->checked_in_at);
    }

    protected function actingAsAdmin(): User
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    /**
     * @return array{0: Batch, 1: Student, 2: Student, 3: Student, 4: Student, 5: User}
     */
    protected function seedClassWithFourStatuses(): array
    {
        $staff = User::factory()->create(['is_active' => true]);

        $session = AcademicSession::query()->create([
            'name' => '2026-27',
            'code' => '2026-27-hub',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 8',
            'code' => 'HUB-8',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        $batch = Batch::query()->create([
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'name' => '8-A',
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);

        $present = $this->makeStudent('Present Student', '9876500001');
        $leave = $this->makeStudent('Leave Student', '9876500002');
        $explicitAbsent = $this->makeStudent('Explicit Absent', '9876500003');
        $unmarked = $this->makeStudent('Unmarked Student', '9876500004');

        $this->attachStudentToBatch($present, $batch, $staff, 'HUB-P');
        $this->attachStudentToBatch($leave, $batch, $staff, 'HUB-L');
        $this->attachStudentToBatch($explicitAbsent, $batch, $staff, 'HUB-A');
        $this->attachStudentToBatch($unmarked, $batch, $staff, 'HUB-U');

        return [$batch, $present, $leave, $explicitAbsent, $unmarked, $staff];
    }

    protected function makeStudent(string $name, string $mobile): Student
    {
        return Student::query()->create([
            'name' => $name,
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => $mobile,
            'status' => StudentStatus::Enrolled,
        ]);
    }

    protected function attachStudentToBatch(Student $student, Batch $batch, User $staff, string $roll): void
    {
        $courseId = $batch->course_id;
        $sessionId = $batch->academic_session_id;

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-'.$roll,
            'course_id' => $courseId,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'CRM-ADM-'.$roll,
            'status' => AdmissionStatus::Approved,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $courseId,
            'academic_session_id' => $sessionId,
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
    }
}
