<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\Punch\AttendanceDisplaySettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AttendanceDisplayTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceDisplaySettingsService $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settings = app(AttendanceDisplaySettingsService::class);

        if (! Schema::hasTable('punch_logs')) {
            Schema::create('punch_logs', function ($table) {
                $table->id();
                $table->string('employee_id', 64);
                $table->date('punch_date');
                $table->time('punch_time');
                $table->string('device_name')->nullable();
                $table->string('area_name')->nullable();
                $table->boolean('is_manual')->default(false);
                $table->timestamps();
            });
        }
    }

    public function test_display_is_hidden_when_disabled(): void
    {
        $this->settings->regenerateToken();
        $this->settings->enable(false);

        $token = (string) $this->settings->token();

        $this->get(route('display.attendance.show', ['token' => $token]))
            ->assertNotFound();
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->settings->regenerateToken();
        $this->settings->enable(true);

        $this->get(route('display.attendance.show', ['token' => 'wrong-token']))
            ->assertForbidden();
    }

    public function test_latest_api_returns_new_machine_punch(): void
    {
        [$student] = $this->createEnrolledStudent('DISP-101');

        $this->settings->regenerateToken();
        $this->settings->enable(true);
        $token = (string) $this->settings->token();

        DB::table('punch_logs')->insert([
            'employee_id' => 'DISP-101',
            'punch_date' => now()->toDateString(),
            'punch_time' => '09:30:00',
            'device_name' => 'Gate-1',
            'is_manual' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson(route('display.attendance.latest', [
            'token' => $token,
            'since' => 0,
        ]));

        $response->assertOk();
        $response->assertJsonPath('punches.0.roll', 'DISP-101');
        $response->assertJsonPath('punches.0.name', $student->name);
        $response->assertJsonPath('punches.0.state', 'IN');
        $response->assertJsonStructure([
            'latest',
            'punches',
            'recent',
            'summary' => ['present_today', 'inside_now', 'checked_out', 'by_batch'],
            'max_id',
            'filters',
        ]);
        $response->assertJsonPath('latest.roll', 'DISP-101');
    }

    public function test_latest_api_returns_manual_punch(): void
    {
        $this->createEnrolledStudent('DISP-202');

        $this->settings->regenerateToken();
        $this->settings->enable(true);
        $token = (string) $this->settings->token();

        DB::table('punch_logs')->insert([
            'employee_id' => 'DISP-202',
            'punch_date' => now()->toDateString(),
            'punch_time' => '10:15:00',
            'device_name' => 'Manual',
            'is_manual' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasTable('attendance_manual_punches')) {
            DB::table('attendance_manual_punches')->insert([
                'enrollment_number' => 'DISP-202',
                'punch_date' => now()->toDateString(),
                'punch_time' => '10:15:00',
                'state' => 'IN',
                'marked_by_user_id' => User::factory()->create()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $response = $this->getJson(route('display.attendance.latest', [
            'token' => $token,
            'since' => 0,
        ]));

        $response->assertOk();
        $response->assertJsonPath('punches.0.state', 'IN');
        $response->assertJsonPath('punches.0.source', 'Manual');
        $response->assertJsonStructure([
            'punches',
            'recent',
            'summary' => ['present_today', 'inside_now', 'checked_out', 'by_batch'],
            'max_id',
            'filters',
        ]);
    }

    public function test_latest_api_includes_class_wise_summary(): void
    {
        $this->createEnrolledStudent('DISP-303');

        $this->settings->regenerateToken();
        $this->settings->enable(true);
        $token = (string) $this->settings->token();

        DB::table('punch_logs')->insert([
            'employee_id' => 'DISP-303',
            'punch_date' => now()->toDateString(),
            'punch_time' => '08:00:00',
            'device_name' => 'Gate-1',
            'is_manual' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson(route('display.attendance.latest', [
            'token' => $token,
            'since' => 0,
        ]));

        $response->assertOk();
        $response->assertJsonPath('summary.present_today', 1);
        $response->assertJsonPath('summary.by_batch.0.present', 1);
        $response->assertJsonCount(1, 'recent');
    }

    public function test_latest_api_live_only_skips_summary(): void
    {
        $this->createEnrolledStudent('DISP-404');

        $this->settings->regenerateToken();
        $this->settings->enable(true);
        $token = (string) $this->settings->token();

        DB::table('punch_logs')->insert([
            'employee_id' => 'DISP-404',
            'punch_date' => now()->toDateString(),
            'punch_time' => '11:00:00',
            'device_name' => 'Gate-1',
            'is_manual' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson(route('display.attendance.latest', [
            'token' => $token,
            'since' => 0,
            'sections' => 'live',
        ]));

        $response->assertOk();
        $response->assertJsonMissingPath('summary');
        $response->assertJsonPath('latest.roll', 'DISP-404');
    }

    public function test_display_page_loads_when_enabled(): void
    {
        $this->settings->regenerateToken();
        $this->settings->enable(true);
        $token = (string) $this->settings->token();

        $this->get(route('display.attendance.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Live attendance display', false)
            ->assertSee('Present today', false)
            ->assertSee('Latest punches', false);
    }

    public function test_unsigned_photo_route_is_rejected(): void
    {
        $this->settings->regenerateToken();
        $this->settings->enable(true);

        $this->get(route('display.attendance.photo', ['document' => 1]))
            ->assertNotFound();
    }

    public function test_signed_photo_route_accepts_valid_signature(): void
    {
        $this->settings->regenerateToken();
        $this->settings->enable(true);

        $url = URL::temporarySignedRoute(
            'display.attendance.photo',
            now()->addHour(),
            [
                'document' => 99999,
                'display' => (string) $this->settings->tokenFingerprint(),
            ],
        );

        $this->get($url)->assertNotFound();
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
            'code' => 'DISP-10',
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
            'name' => 'Display Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876543210',
            'status' => StudentStatus::Enrolled,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-DISP',
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'CRM-ADM-DISP',
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
}
