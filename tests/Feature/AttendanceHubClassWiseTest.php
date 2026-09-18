<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\BatchStatus;
use App\Enums\CallDirection;
use App\Enums\CallStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrolledCallPurpose;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\WhoAnswered;
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
use App\Models\StudentCall;
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
        $this->assertSame(2, $overview['students_absent']);
        $this->assertSame(3, $overview['students_manual_marked']);
        $this->assertSame(0, $overview['students_auto_marked']);

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

        $leaveRoster = app(AttendanceHubOverviewService::class)
            ->classBucketRoster($batch->id, '2026-09-11', 'leave');
        $this->assertSame([$leave->id], collect($leaveRoster['students'])->pluck('id')->all());
        $this->assertTrue(collect($leaveRoster['students'])->every(fn (array $s): bool => $s['can_mark'] === false));
        $this->assertSame('Sick', $leaveRoster['students'][0]['leave_reason']);
    }

    public function test_hub_opens_on_todays_overview_without_a_separate_filter_card(): void
    {
        $this->actingAsAdmin();

        Livewire::test(AttendanceHubPage::class)
            ->assertSuccessful()
            ->assertSee('Today’s overview')
            ->assertSee('All')
            ->assertSee('Students')
            ->assertSee('Staff')
            ->assertDontSee('Show in list')
            ->assertDontSee('Today’s overview for students and staff');
    }

    public function test_picked_date_is_shown_as_that_days_overview_not_today(): void
    {
        $this->travelTo('2026-09-18 10:00:00');
        $this->actingAsAdmin();

        Livewire::test(AttendanceHubPage::class)
            ->set('overviewDate', '2026-09-11')
            ->assertSee('Overview for 11 Sep 2026')
            ->assertDontSee('Today’s overview');
    }

    public function test_staff_filter_hides_class_wise_students(): void
    {
        $this->travelTo('2026-09-11 10:00:00');
        $this->seedClassWithFourStatuses();
        $this->actingAsAdmin();

        Livewire::test(AttendanceHubPage::class)
            ->set('overviewDate', '2026-09-11')
            ->assertSee('Class-wise (students)')
            ->assertSee('Students present')
            ->call('setFeedType', 'staff')
            ->assertSet('feedType', 'staff')
            ->assertDontSee('Class-wise (students)')
            ->assertDontSee('Students present')
            ->assertSee('Staff present');
    }

    public function test_absent_drill_shows_only_attendance_calls_for_that_day(): void
    {
        $this->travelTo('2026-09-11 10:00:00');

        [$batch, , , , $unmarked, $staff] = $this->seedClassWithFourStatuses();

        StudentCall::query()->create([
            'student_id' => $unmarked->id,
            'user_id' => $staff->id,
            'called_at' => '2026-09-11 09:15:00',
            'call_direction' => CallDirection::Outgoing,
            'call_status' => CallStatus::Connected,
            'call_purpose' => EnrolledCallPurpose::Attendance,
            'who_answered' => WhoAnswered::Father,
            'call_notes' => 'Will come after lunch',
            'tags' => [EnrolledCallPurpose::Attendance->value],
        ]);
        StudentCall::query()->create([
            'student_id' => $unmarked->id,
            'user_id' => $staff->id,
            'called_at' => '2026-09-11 09:40:00',
            'call_direction' => CallDirection::Outgoing,
            'call_status' => CallStatus::Connected,
            'call_purpose' => EnrolledCallPurpose::FeeQuery,
            'who_answered' => WhoAnswered::Mother,
            'call_notes' => 'Fee reminder — ignore on hub',
            'tags' => [EnrolledCallPurpose::FeeQuery->value],
        ]);

        $absentRoster = app(AttendanceHubOverviewService::class)
            ->classBucketRoster($batch->id, '2026-09-11', 'absent');

        $row = collect($absentRoster['students'])->firstWhere('id', $unmarked->id);
        $this->assertNotNull($row);
        $this->assertCount(1, $row['attendance_calls']);
        $this->assertSame('Will come after lunch', $row['attendance_calls'][0]['notes']);
        $this->assertSame('Father', $row['attendance_calls'][0]['who']);
        $this->assertSame($staff->name, $row['attendance_calls'][0]['staff']);

        $otherAbsent = collect($absentRoster['students'])->first(
            fn (array $s): bool => $s['id'] !== $unmarked->id,
        );
        $this->assertNotNull($otherAbsent);
        $this->assertSame([], $otherAbsent['attendance_calls']);

        $this->actingAsAdmin();

        Livewire::test(AttendanceHubPage::class)
            ->set('overviewDate', '2026-09-11')
            ->call('openClassDrill', $batch->id, 'absent')
            ->assertSee('Will come after lunch')
            ->assertSee('Spoke to: Father')
            ->assertSee('No attendance call made yet')
            ->assertDontSee('Fee reminder — ignore on hub');
    }

    public function test_absent_drill_shows_not_connected_attendance_call(): void
    {
        $this->travelTo('2026-09-11 10:00:00');

        [$batch, , , , $unmarked, $staff] = $this->seedClassWithFourStatuses();

        StudentCall::query()->create([
            'student_id' => $unmarked->id,
            'user_id' => $staff->id,
            'called_at' => '2026-09-11 09:20:00',
            'call_direction' => CallDirection::Outgoing,
            'call_status' => CallStatus::NoAnswer,
            'call_purpose' => EnrolledCallPurpose::Attendance,
            'tags' => [EnrolledCallPurpose::Attendance->value],
        ]);

        $absentRoster = app(AttendanceHubOverviewService::class)
            ->classBucketRoster($batch->id, '2026-09-11', 'absent');

        $row = collect($absentRoster['students'])->firstWhere('id', $unmarked->id);
        $this->assertNotNull($row);
        $this->assertCount(1, $row['attendance_calls']);
        $this->assertFalse($row['attendance_calls'][0]['connected']);
        $this->assertSame('No Answer', $row['attendance_calls'][0]['status']);

        $this->actingAsAdmin();

        Livewire::test(AttendanceHubPage::class)
            ->set('overviewDate', '2026-09-11')
            ->call('openClassDrill', $batch->id, 'absent')
            ->assertSee('Call made, not connected')
            ->assertSee('No Answer');
    }

    public function test_overview_auto_marked_excludes_hand_marks_and_lists_open_names(): void
    {
        $this->travelTo('2026-09-11 10:00:00');

        [$batch, $present, $leave, $explicitAbsent, $unmarked, $staff] = $this->seedClassWithFourStatuses();

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $present->id,
            'attendance_date' => '2026-09-11',
            'status' => AttendanceStatus::Present,
            'punch_source' => 'biometric',
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
            'punch_source' => 'manual',
            'marked_by_user_id' => $staff->id,
        ]);

        $service = app(AttendanceHubOverviewService::class);
        $overview = $service->overview('2026-09-11');

        $this->assertSame(2, $overview['students_absent']);
        $this->assertSame(1, $overview['students_auto_marked']);
        $this->assertSame(2, $overview['students_manual_marked']);

        $manual = $service->overviewStudentList('2026-09-11', 'manual');
        $this->assertEqualsCanonicalizing(
            [$leave->id, $explicitAbsent->id],
            collect($manual['students'])->pluck('id')->all(),
        );

        $onLeave = $service->overviewStudentList('2026-09-11', 'leave');
        $this->assertSame([$leave->id], collect($onLeave['students'])->pluck('id')->all());
        $this->assertSame('HUB-L', $onLeave['students'][0]['roll']);
        $this->assertSame('Sick', $onLeave['students'][0]['leave_reason']);

        $this->actingAsAdmin();

        Livewire::test(AttendanceHubPage::class)
            ->set('overviewDate', '2026-09-11')
            ->call('openOverviewList', 'leave')
            ->assertSet('overviewList', 'leave')
            ->assertSee('Leave Student')
            ->assertSee('Sick')
            ->assertDontSeeHtml('>Mark present</button>')
            ->call('closeOverviewList')
            ->call('openClassDrill', $batch->id, 'leave')
            ->assertSet('classDrillBucket', 'leave')
            ->assertSee('Leave Student')
            ->assertSee('Sick')
            ->assertDontSeeHtml('>Mark present</button>')
            ->assertDontSee($unmarked->name);
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
            ->assertSet('leaveFromDate', '2026-09-11')
            ->assertSet('leaveToDate', '2026-09-11')
            ->set('leaveReasonTag', AttendanceLeaveReasons::tags()[0] ?? 'Personal work')
            ->set('leaveReasonCustom', '')
            ->set('leaveToDate', '2026-09-13')
            ->call('confirmHubLeave')
            ->assertSet('leaveStudentId', null);

        $leaveDates = Attendance::query()
            ->where('student_id', $leaveStudent->id)
            ->where('status', AttendanceStatus::Leave)
            ->orderBy('attendance_date')
            ->pluck('attendance_date')
            ->map(fn ($d) => \Illuminate\Support\Carbon::parse($d)->toDateString())
            ->all();

        $this->assertSame(['2026-09-11', '2026-09-12', '2026-09-13'], $leaveDates);
    }

    public function test_hub_can_mark_present_via_manual_in_from_absent_drill(): void
    {
        $this->travelTo('2026-09-11 10:00:00');

        [$batch, , , , $unmarked] = $this->seedClassWithFourStatuses();
        $this->actingAsAdmin();

        Livewire::test(AttendanceHubPage::class)
            ->set('overviewDate', '2026-09-11')
            ->call('openClassDrill', $batch->id, 'absent')
            ->call('startHubPresent', $unmarked->id)
            ->assertSet('presentStudentId', $unmarked->id)
            ->assertSee('Send parent message for this time');

        $this->assertNull(
            Attendance::query()->where('student_id', $unmarked->id)->first(),
        );

        Livewire::test(AttendanceHubPage::class)
            ->set('overviewDate', '2026-09-11')
            ->call('openClassDrill', $batch->id, 'absent')
            ->call('startHubPresent', $unmarked->id)
            ->set('presentTime', '08:15')
            ->set('presentNotify', false)
            ->call('confirmHubPresent')
            ->assertSet('presentStudentId', null);

        $row = Attendance::query()
            ->where('student_id', $unmarked->id)
            ->whereDate('attendance_date', '2026-09-11')
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(AttendanceStatus::Present, $row->status);
        $this->assertSame('manual', $row->punch_source);
        $this->assertSame('08:15:00', $row->checked_in_at?->format('H:i:s'));
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
