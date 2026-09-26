<?php

namespace Tests\Feature;

use App\Enums\BatchStaffRole;
use App\Enums\BatchStatus;
use App\Enums\CallDirection;
use App\Enums\CallStatus;
use App\Enums\CourseStatus;
use App\Enums\CrmPermission;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\StudentStatus;
use App\Filament\Pages\StaffAttendancePage;
use App\Filament\Pages\StudentProfilePage;
use App\Filament\Resources\Students\StudentResource;
use App\Filament\Widgets\DashboardHeroWidget;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentCall;
use App\Models\User;
use App\Services\AttendanceHubOverviewService;
use App\Services\BatchStaffAssignmentService;
use App\Services\DashboardOpsService;
use App\Services\Punch\PunchBatchRosterService;
use App\Services\CrmPermissionSyncService;
use App\Services\StudentActivityTimelineService;
use App\Services\StudentSearchService;
use App\Support\CrmAccess;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeacherAssignedClassAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleName::SuperAdmin->value);
        Role::findOrCreate(RoleName::Staff->value);
        app(CrmPermissionSyncService::class)->sync();
    }

    public function test_teacher_sees_only_students_in_assigned_class(): void
    {
        $data = $this->seedTwoClasses();
        $this->actingAs($data['teacher']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $listed = StudentResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($data['ownStudent']->id, $listed);
        $this->assertNotContains($data['otherStudent']->id, $listed);

        $search = app(StudentSearchService::class);
        $own = $search->search($data['ownStudent']->mobile, null, null, null, $data['teacher']);
        $other = $search->search($data['otherStudent']->mobile, null, null, null, $data['teacher']);

        $this->assertSame(StudentSearchService::OUTCOME_FOUND, $own['outcome']);
        $this->assertSame($data['ownStudent']->id, $own['student']?->id);
        $this->assertSame(StudentSearchService::OUTCOME_NOT_FOUND, $other['outcome']);

        $classes = app(BatchStaffAssignmentService::class);
        $this->assertTrue($classes->canViewStudent($data['teacher'], $data['ownStudent']));
        $this->assertFalse($classes->canViewStudent($data['teacher'], $data['otherStudent']));
        $this->assertTrue($classes->canAccessClass($data['teacher'], $data['ownBatch']->id));
        $this->assertFalse($classes->canAccessClass($data['teacher'], $data['otherBatch']->id));

        $options = $classes->activeBatchOptionsFor($data['teacher']);
        $this->assertArrayHasKey($data['ownBatch']->id, $options);
        $this->assertArrayNotHasKey($data['otherBatch']->id, $options);

        $this->assertFalse(StaffAttendancePage::canAccess());

        $overview = app(AttendanceHubOverviewService::class)->overview(now()->toDateString(), $data['teacher']);
        $batchIds = array_column($overview['class_rows'], 'batch_id');
        $this->assertSame([$data['ownBatch']->id], $batchIds);
        $this->assertSame(0, $overview['staff_expected']);
    }

    public function test_teacher_cannot_open_another_class_student_profile(): void
    {
        $data = $this->seedTwoClasses();
        $this->actingAs($data['teacher']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(StudentProfilePage::class, ['record' => $data['otherStudent']])
            ->assertForbidden();
    }

    public function test_teacher_can_open_assigned_student_profile(): void
    {
        $data = $this->seedTwoClasses();
        $this->actingAs($data['teacher']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(StudentProfilePage::class, ['record' => $data['ownStudent']])
            ->assertSuccessful()
            ->assertSee($data['ownStudent']->name);
    }

    public function test_teacher_does_not_see_call_log_on_student_profile(): void
    {
        $data = $this->seedTwoClasses();

        StudentCall::query()->create([
            'student_id' => $data['ownStudent']->id,
            'user_id' => $data['teacher']->id,
            'called_at' => now()->subHour(),
            'call_direction' => CallDirection::Outgoing,
            'call_status' => CallStatus::Connected,
            'call_notes' => 'Teacher must not see this call note',
        ]);

        $data['ownStudent']->update([
            'total_calls' => 1,
            'last_call_at' => now()->subHour(),
            'last_call_status' => CallStatus::Connected,
            'last_call_notes' => 'Teacher must not see this call note',
        ]);

        $this->actingAs($data['teacher']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertFalse($data['teacher']->canCrm(CrmPermission::LeadsCall));

        $types = collect(app(StudentActivityTimelineService::class)
            ->forStudent($data['ownStudent']->fresh(), $data['teacher'], 40)['items'])
            ->pluck('type')
            ->all();
        $this->assertNotContains('call', $types);

        Livewire::test(StudentProfilePage::class, ['record' => $data['ownStudent']->fresh()])
            ->assertSuccessful()
            ->assertDontSee('Teacher must not see this call note')
            ->assertDontSee('Last call')
            ->assertDontSee('Not called yet');
    }

    public function test_coordinator_still_sees_every_class_and_student(): void
    {
        $data = $this->seedTwoClasses();
        $coordinator = User::factory()->create(['is_active' => true]);
        $coordinator->assignRole(StaffJobRole::AcademicCoordinator->value);
        $this->actingAs($coordinator);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $listed = StudentResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($data['ownStudent']->id, $listed);
        $this->assertContains($data['otherStudent']->id, $listed);

        $options = app(BatchStaffAssignmentService::class)->activeBatchOptionsFor($coordinator);
        $this->assertArrayHasKey($data['ownBatch']->id, $options);
        $this->assertArrayHasKey($data['otherBatch']->id, $options);
        $this->assertTrue(StaffAttendancePage::canAccess());
    }

    public function test_teacher_home_counts_only_the_assigned_class(): void
    {
        $data = $this->seedTwoClasses();
        $this->actingAs($data['teacher']);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(DashboardHeroWidget::class)
            ->assertSuccessful()
            ->assertSee('1 students enrolled')
            ->assertDontSee('2 students enrolled');

        $attention = app(DashboardOpsService::class)->attentionSnapshot(null, $data['teacher']);
        $this->assertSame(1, $attention['attendance_expected']);

        $coordinator = User::factory()->create(['is_active' => true]);
        $coordinator->assignRole(StaffJobRole::AcademicCoordinator->value);
        $coordinatorAttention = app(DashboardOpsService::class)->attentionSnapshot(null, $coordinator);
        $this->assertSame(2, $coordinatorAttention['attendance_expected']);
    }

    public function test_teacher_attendance_card_hides_mobile_until_the_staff_switch_is_on(): void
    {
        $data = $this->seedTwoClasses();
        $this->actingAs($data['teacher']);

        $hidden = app(PunchBatchRosterService::class)->rosterForBatch($data['ownBatch']->id, now()->toDateString());
        $this->assertSame('Riya Sharma', $hidden['absent'][0]['student_name']);
        $this->assertNull($hidden['absent'][0]['mobile']);

        CrmAccess::setStudentMobileVisibility($data['teacher'], true);

        $shown = app(PunchBatchRosterService::class)->rosterForBatch($data['ownBatch']->id, now()->toDateString());
        $this->assertSame('9876501111', $shown['absent'][0]['mobile']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);
        $this->actingAs($admin);

        $adminRoster = app(PunchBatchRosterService::class)->rosterForBatch($data['ownBatch']->id, now()->toDateString());
        $this->assertSame('9876501111', $adminRoster['absent'][0]['mobile']);
    }

    /**
     * @return array{
     *     teacher: User,
     *     ownBatch: Batch,
     *     otherBatch: Batch,
     *     ownStudent: Student,
     *     otherStudent: Student
     * }
     */
    protected function seedTwoClasses(): array
    {
        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->assignRole(StaffJobRole::Teacher->value);

        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27-teacher-scope',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 11',
            'code' => 'CLS-11-SCOPE',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        $ownBatch = Batch::query()->create([
            'name' => 'Class 11 - A',
            'section' => 'A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        $otherBatch = Batch::query()->create([
            'name' => 'Class 11 - B',
            'section' => 'B',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        BatchStaffAssignment::query()->create([
            'batch_id' => $ownBatch->id,
            'user_id' => $teacher->id,
            'role' => BatchStaffRole::SubjectTeacher,
            'course_subject_id' => null,
        ]);

        $ownStudent = Student::query()->create([
            'name' => 'Riya Sharma',
            'mobile' => '9876501111',
            'status' => StudentStatus::Enrolled,
        ]);
        $otherStudent = Student::query()->create([
            'name' => 'Aman Verma',
            'mobile' => '9876502222',
            'status' => StudentStatus::Enrolled,
        ]);

        BatchStudent::query()->create([
            'batch_id' => $ownBatch->id,
            'student_id' => $ownStudent->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $teacher->id,
        ]);
        BatchStudent::query()->create([
            'batch_id' => $otherBatch->id,
            'student_id' => $otherStudent->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $teacher->id,
        ]);

        return compact('teacher', 'ownBatch', 'otherBatch', 'ownStudent', 'otherStudent');
    }
}
