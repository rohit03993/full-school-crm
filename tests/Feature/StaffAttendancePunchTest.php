<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\StaffAttendance;
use App\Models\StaffProfile;
use App\Models\Student;
use App\Models\User;
use App\Services\Punch\ManualStaffAttendanceService;
use App\Services\Punch\PunchAttendanceProcessor;
use App\Services\StaffBulkImportService;
use App\Support\BiometricPinCollision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffAttendancePunchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleName::SuperAdmin->value);
        Role::findOrCreate(RoleName::Staff->value);

        if (! Schema::hasTable('punch_logs')) {
            Schema::create('punch_logs', function ($table) {
                $table->id();
                $table->string('employee_id', 64);
                $table->date('punch_date');
                $table->time('punch_time');
                $table->string('device_name')->nullable();
                $table->boolean('is_manual')->default(false);
                $table->timestamps();
            });
        }

        Setting::setValue('attendance.last_processed_punch_log_id', '0', 'attendance');
        Setting::setValue('whatsapp.staff_punch_autosend_enabled', '0', 'whatsapp');
    }

    public function test_staff_punch_writes_staff_attendance_not_student(): void
    {
        $staff = $this->createStaffWithCode('STF100');

        DB::table('punch_logs')->insert([
            'employee_id' => 'STF100',
            'punch_date' => '2026-08-05',
            'punch_time' => '09:05:00',
            'device_name' => 'Gate',
            'is_manual' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stats = app(PunchAttendanceProcessor::class)->processPending();

        $this->assertSame(1, $stats['synced']);
        $this->assertSame(1, StaffAttendance::query()->count());

        $row = StaffAttendance::query()->first();
        $this->assertSame($staff->id, $row->user_id);
        $this->assertSame(AttendanceStatus::Present, $row->status);
        $this->assertNotNull($row->checked_in_at);
    }

    public function test_student_live_dashboard_hides_staff_punches(): void
    {
        $this->createStaffWithCode('STF005');
        $this->createEnrolledStudent('ROLL-1');

        DB::table('punch_logs')->insert([
            [
                'employee_id' => 'STF005',
                'punch_date' => '2026-08-12',
                'punch_time' => '13:01:06',
                'device_name' => 'Face Camera Kiosk',
                'is_manual' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'employee_id' => 'ROLL-1',
                'punch_date' => '2026-08-12',
                'punch_time' => '13:05:00',
                'device_name' => 'Face Camera Kiosk',
                'is_manual' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $dashboard = app(\App\Services\Punch\LivePunchDashboardService::class)->dashboardForDate('2026-08-12');
        $rolls = collect($dashboard['rows'])->pluck('roll')->all();

        $this->assertContains('ROLL-1', $rolls);
        $this->assertNotContains('STF005', $rolls);
        $this->assertSame(1, $dashboard['stats']['staff_hidden']);
        $this->assertNotSame('Unmapped punch', collect($dashboard['rows'])->first()['student_name'] ?? null);
    }

    public function test_student_roll_still_takes_priority_over_staff_code(): void
    {
        $this->createStaffWithCode('ROLL99');
        $this->createEnrolledStudent('ROLL99');

        DB::table('punch_logs')->insert([
            'employee_id' => 'ROLL99',
            'punch_date' => '2026-08-05',
            'punch_time' => '09:10:00',
            'device_name' => 'Gate',
            'is_manual' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(PunchAttendanceProcessor::class)->processPending();

        $this->assertSame(0, StaffAttendance::query()->count());
    }

    public function test_staff_import_rejects_code_matching_student_roll(): void
    {
        $admin = $this->createSuperAdmin();
        $this->createEnrolledStudent('STU777');

        $this->assertTrue(BiometricPinCollision::staffCodeCollidesWithStudentRoll('STU777'));

        $result = app(StaffBulkImportService::class)->importRows($admin, [
            ['STU777', 'Teacher One', '9876501111', 'Teacher', ''],
        ], [
            'staff_id' => 0,
            'name' => 1,
            'mobile' => 2,
            'designation' => 3,
            'email' => 4,
        ]);

        $this->assertSame(0, $result['imported']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('student roll', $result['errors'][0]);
    }

    public function test_staff_import_creates_user_and_profile(): void
    {
        $admin = $this->createSuperAdmin();

        $result = app(StaffBulkImportService::class)->importRows($admin, [
            ['STF200', 'Anita Sharma', '9876502222', 'Counsellor', ''],
        ], [
            'staff_id' => 0,
            'name' => 1,
            'mobile' => 2,
            'designation' => 3,
            'email' => 4,
        ]);

        $this->assertSame(1, $result['imported']);
        $this->assertSame([], $result['errors']);

        $user = User::query()->where('mobile', '9876502222')->first();
        $this->assertNotNull($user);
        $this->assertSame('STF200', $user->staffProfile?->employee_code);
        $this->assertTrue($user->hasRole(RoleName::Staff->value));
    }

    public function test_manual_staff_in_and_out(): void
    {
        $admin = $this->createSuperAdmin();
        $staff = $this->createStaffWithCode('STF300');
        $date = now()->toDateString();
        $manual = app(ManualStaffAttendanceService::class);

        $in = $manual->manualIn($staff, $date, $admin);
        $this->assertTrue($in['ok']);
        $this->assertTrue($manual->isInside($staff, $date));

        $row = StaffAttendance::query()->where('user_id', $staff->id)->first();
        $this->assertNotNull($row);
        $this->assertSame('manual', $row->punch_source);
        $this->assertSame($admin->id, $row->marked_by_user_id);

        $out = $manual->manualOut($staff, $date, $admin);
        $this->assertTrue($out['ok']);
        $this->assertFalse($manual->isInside($staff, $date));

        $row->refresh();
        $this->assertNotNull($row->checked_out_at);
    }

    public function test_manual_staff_rejects_backdated_date(): void
    {
        $admin = $this->createSuperAdmin();
        $staff = $this->createStaffWithCode('STF301');
        $manual = app(ManualStaffAttendanceService::class);

        $result = $manual->manualIn($staff, now()->subDay()->toDateString(), $admin);

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('today', strtolower($result['message']));
        $this->assertSame(0, StaffAttendance::query()->count());
    }

    public function test_imported_staff_appear_on_staff_resource_list(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        app(StaffBulkImportService::class)->importRows($admin, [
            ['STF400', 'Listed Teacher', '9876504000', 'Teacher', ''],
        ], [
            'staff_id' => 0,
            'name' => 1,
            'mobile' => 2,
            'designation' => 3,
            'email' => 4,
        ]);

        $user = User::query()->where('mobile', '9876504000')->first();
        $this->assertNotNull($user);

        $ids = \App\Filament\Resources\Staff\StaffResource::getEloquentQuery()->pluck('id')->all();
        $this->assertContains($user->id, $ids);
    }

    protected function createSuperAdmin(): User
    {
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createStaffWithCode(string $code): User
    {
        $user = User::factory()->create([
            'name' => 'Staff '.$code,
            'mobile' => '9'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $user->assignRole(RoleName::Staff->value);
        StaffProfile::query()->create([
            'user_id' => $user->id,
            'employee_code' => $code,
            'designation' => 'Teacher',
            'mobile' => $user->mobile,
        ]);

        return $user->fresh('staffProfile');
    }

    protected function createEnrolledStudent(string $roll): Student
    {
        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'STAFF-ATT-10',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        $student = Student::query()->create([
            'name' => 'Student '.$roll,
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '9'.str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            'status' => StudentStatus::Enrolled,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'ENQ-'.$roll,
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-'.$roll,
            'status' => AdmissionStatus::Approved,
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'enrollment_number' => $roll,
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        return $student->fresh('activeEnrollment');
    }
}
