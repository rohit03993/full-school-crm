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
use App\Models\BiometricDevice;
use App\Models\BiometricPunch;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\FaceVerificationRequest;
use App\Models\Student;
use App\Models\User;
use App\Services\FaceVerify\FaceVerifyGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FaceVerifyIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

        config([
            'face_verify.enabled' => true,
            'face_verify.api_url' => 'https://face-api.test',
            'face_verify.service_token' => 'crm-service-token',
            'face_verify.callback_secret' => 'crm-callback-secret',
            'face_verify.timeout_seconds' => 30,
            'biometric.process_inline' => false,
        ]);
    }

    public function test_gated_device_holds_punch_and_creates_face_request(): void
    {
        $this->createEnrolledStudent('STU001');

        $device = BiometricDevice::query()->create([
            'name' => 'Gate 1',
            'serial_number' => 'K40FACE001',
            'location' => 'Main',
            'is_active' => true,
            'requires_face_verify' => true,
            'face_verify_device_id' => '1014e85f-df0e-44bf-9029-3e4710e0e268',
        ]);

        Http::fake([
            'https://face-api.test/verification-requests' => Http::response([
                'request_id' => 'face-req-1',
                'student_id' => 'face-stu-1',
                'status' => 'PENDING',
            ], 200),
        ]);

        $body = "STU001\t2026-07-17 09:00:00\t0\t1\t0";

        $response = $this->call(
            'POST',
            '/iclock/cdata?SN=K40FACE001&table=ATTLOG&Stamp=1',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $body,
        );

        $response->assertOk();

        $this->assertDatabaseHas('biometric_punches', [
            'serial_number' => 'K40FACE001',
            'user_pin' => 'STU001',
            'process_status' => BiometricPunch::STATUS_PENDING,
        ]);

        $this->assertSame(0, DB::table('punch_logs')->count());

        $this->assertDatabaseHas('face_verification_requests', [
            'enrollment_number' => 'STU001',
            'status' => FaceVerificationRequest::STATUS_PENDING,
            'face_request_id' => 'face-req-1',
            'biometric_device_id' => $device->id,
        ]);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://face-api.test/verification-requests'
                && $request['enrollment_number'] === 'STU001'
                && $request['device_id'] === '1014e85f-df0e-44bf-9029-3e4710e0e268';
        });
    }

    public function test_non_gated_device_still_mirrors_immediately(): void
    {
        BiometricDevice::query()->create([
            'name' => 'Reception',
            'serial_number' => 'K40PLAIN001',
            'is_active' => true,
            'requires_face_verify' => false,
        ]);

        $body = "78979877\t2026-07-17 10:15:00\t0\t1\t0";

        $this->call(
            'POST',
            '/iclock/cdata?SN=K40PLAIN001&table=ATTLOG&Stamp=1',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'text/plain'],
            $body,
        )->assertOk();

        $this->assertDatabaseHas('biometric_punches', [
            'serial_number' => 'K40PLAIN001',
            'process_status' => BiometricPunch::STATUS_MIRRORED,
        ]);

        $this->assertTrue(
            DB::table('punch_logs')
                ->where('employee_id', '78979877')
                ->where('punch_date', '2026-07-17')
                ->where('punch_time', '10:15:00')
                ->exists()
        );

        $this->assertSame(0, FaceVerificationRequest::query()->count());
    }

    public function test_approve_callback_writes_punch_log_and_is_idempotent(): void
    {
        [$student] = $this->createEnrolledStudent('STU001');

        $device = BiometricDevice::query()->create([
            'name' => 'Gate 1',
            'serial_number' => 'K40FACE001',
            'location' => 'Main',
            'is_active' => true,
            'requires_face_verify' => true,
            'face_verify_device_id' => '1014e85f-df0e-44bf-9029-3e4710e0e268',
        ]);

        $punch = BiometricPunch::query()->create([
            'biometric_device_id' => $device->id,
            'serial_number' => $device->serial_number,
            'user_pin' => 'STU001',
            'punched_at' => '2026-07-17 09:00:00',
            'process_status' => BiometricPunch::STATUS_PENDING,
        ]);

        $request = FaceVerificationRequest::query()->create([
            'biometric_punch_id' => $punch->id,
            'biometric_device_id' => $device->id,
            'student_id' => $student->id,
            'enrollment_number' => 'STU001',
            'face_device_id' => $device->face_verify_device_id,
            'face_request_id' => 'face-req-1',
            'status' => FaceVerificationRequest::STATUS_PENDING,
            'punched_at' => '2026-07-17 09:00:00',
            'requested_at' => now(),
        ]);

        $payload = [
            'request_id' => 'face-req-1',
            'crm_request_id' => $request->id,
            'student_id' => 'face-stu-1',
            'enrollment_number' => 'STU001',
            'device_id' => $device->face_verify_device_id,
            'score' => 0.51,
            'status' => 'PASS',
            'timestamp' => '2026-07-17T09:00:05+00:00',
        ];

        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', $raw, 'crm-callback-secret');

        $this->call(
            'POST',
            '/api/face-verify/approve',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer crm-service-token',
                'HTTP_X_FACE_VERIFY_SIGNATURE' => $signature,
            ],
            $raw,
        )->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1, DB::table('punch_logs')->count());
        $this->assertDatabaseHas('face_verification_requests', [
            'id' => $request->id,
            'status' => FaceVerificationRequest::STATUS_PASS,
        ]);
        $this->assertDatabaseHas('biometric_punches', [
            'id' => $punch->id,
            'process_status' => BiometricPunch::STATUS_MIRRORED,
        ]);

        $this->call(
            'POST',
            '/api/face-verify/approve',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer crm-service-token',
                'HTTP_X_FACE_VERIFY_SIGNATURE' => $signature,
            ],
            $raw,
        )->assertOk()->assertJson(['ok' => true]);

        $this->assertSame(1, DB::table('punch_logs')->count());
    }

    public function test_approve_rejects_invalid_signature(): void
    {
        $payload = json_encode([
            'request_id' => 'x',
            'crm_request_id' => 'y',
            'status' => 'PASS',
        ]);

        $this->call(
            'POST',
            '/api/face-verify/approve',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer crm-service-token',
                'HTTP_X_FACE_VERIFY_SIGNATURE' => 'bad-signature',
            ],
            $payload,
        )->assertUnauthorized();
    }

    public function test_sweep_marks_pending_as_timeout(): void
    {
        $request = FaceVerificationRequest::query()->create([
            'enrollment_number' => 'STU001',
            'status' => FaceVerificationRequest::STATUS_PENDING,
            'punched_at' => now()->subMinutes(5),
            'requested_at' => now()->subMinutes(5),
        ]);

        $count = app(FaceVerifyGateService::class)->markTimedOutPending();

        $this->assertSame(1, $count);
        $this->assertSame(
            FaceVerificationRequest::STATUS_TIMEOUT,
            $request->fresh()->status,
        );
    }

    /**
     * @return array{0: Student, 1: Batch}
     */
    private function createEnrolledStudent(string $roll): array
    {
        $staff = User::factory()->create(['is_active' => true]);

        $session = AcademicSession::query()->create([
            'name' => '2026-27',
            'code' => '2026-27-FV',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'FV-10',
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
            'name' => 'Demo Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876500123',
            'status' => StudentStatus::Enrolled,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-FV1',
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'CRM-ADM-FV1',
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
