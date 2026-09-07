<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\BatchStatus;
use App\Enums\CallDirection;
use App\Enums\CallStatus;
use App\Enums\CourseStatus;
use App\Enums\CrmPermission;
use App\Enums\EnrolledCallPurpose;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentCall;
use App\Models\User;
use App\Services\CrmPermissionSyncService;
use App\Services\StudentActivityTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentActivityTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(CrmPermissionSyncService::class)->sync();
    }

    public function test_timeline_orders_newest_first_with_smart_call_and_fee_labels(): void
    {
        [$staff, $student, $feeStructure, $batch] = $this->seedStudentWithStaff();

        StudentCall::query()->create([
            'student_id' => $student->id,
            'user_id' => $staff->id,
            'called_at' => now()->subHours(2),
            'call_direction' => CallDirection::Outgoing,
            'call_status' => CallStatus::Connected,
            'call_purpose' => EnrolledCallPurpose::Attendance,
            'call_notes' => 'Absent yesterday',
        ]);

        Payment::query()->create([
            'fee_structure_id' => $feeStructure->id,
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'amount' => 5000,
            'payment_mode' => PaymentMode::Cash,
            'receipt_number' => 'RCP-1',
            'proof_image_path' => 'proofs/tl.jpg',
            'status' => PaymentStatus::Active,
            'added_by_user_id' => $staff->id,
            'created_at' => now()->subHour(),
        ]);

        Attendance::query()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'attendance_date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
            'checked_in_at' => now()->subHours(5),
            'marked_by_user_id' => $staff->id,
        ]);

        $result = app(StudentActivityTimelineService::class)->forStudent($student, $staff, 40);

        $this->assertNotEmpty($result['items']);
        $this->assertTrue(
            collect($result['items'])->contains(fn (array $i): bool => str_contains($i['title'], 'Call · Attendance'))
        );
        $this->assertTrue(
            collect($result['items'])->contains(fn (array $i): bool => str_contains($i['title'], 'Fee remitted'))
        );
        $this->assertTrue(
            collect($result['items'])->contains(fn (array $i): bool => str_contains($i['title'], 'Punch IN'))
        );

        $times = collect($result['items'])->map(fn (array $i) => $i['occurred_at']->getTimestamp())->all();
        $sorted = $times;
        rsort($sorted);
        $this->assertSame($sorted, $times);

        $call = collect($result['items'])->first(fn (array $i): bool => $i['type'] === 'call');
        $this->assertSame('calls', $call['tab']);
        $this->assertSame('Absent yesterday', $call['summary']);
    }

    public function test_timeline_hides_fees_without_fee_permission(): void
    {
        [$staff, $student, $feeStructure] = $this->seedStudentWithStaff();
        $staff->syncRoles([StaffJobRole::Counsellor->value]);

        Payment::query()->create([
            'fee_structure_id' => $feeStructure->id,
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'amount' => 1000,
            'payment_mode' => PaymentMode::Cash,
            'receipt_number' => 'RCP-2',
            'proof_image_path' => 'proofs/tl2.jpg',
            'status' => PaymentStatus::Active,
            'added_by_user_id' => $staff->id,
        ]);

        StudentCall::query()->create([
            'student_id' => $student->id,
            'user_id' => $staff->id,
            'called_at' => now(),
            'call_direction' => CallDirection::Outgoing,
            'call_status' => CallStatus::Connected,
            'call_purpose' => EnrolledCallPurpose::FeeQuery,
            'call_notes' => 'Asked about fees',
        ]);

        $this->assertFalse($staff->fresh()->canCrm(CrmPermission::FeesCollect));

        $result = app(StudentActivityTimelineService::class)->forStudent($student, $staff->fresh(), 40);
        $types = collect($result['items'])->pluck('type')->all();

        $this->assertContains('call', $types);
        $this->assertNotContains('fee', $types);
    }

    /**
     * @return array{0: User, 1: Student, 2: FeeStructure, 3: Batch}
     */
    protected function seedStudentWithStaff(): array
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole(RoleName::SuperAdmin->value);

        $session = AcademicSession::query()->create([
            'name' => '2026-27',
            'code' => '2026-27-tl',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 11',
            'code' => 'CLS-11-TL',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        $batch = Batch::query()->create([
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'name' => '11-A Timeline',
            'status' => BatchStatus::Active,
        ]);

        $student = Student::query()->create([
            'name' => 'Timeline Student',
            'mobile' => '9000007788',
            'gender' => Gender::Male,
            'status' => StudentStatus::Enrolled,
            'lead_source' => LeadSource::WalkIn,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'enquiry_number' => 'ENQ-TL-1',
            'lead_source' => LeadSource::WalkIn,
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-TL-1',
            'status' => \App\Enums\AdmissionStatus::Approved,
        ]);

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'enrollment_number' => 'ROLL-TL-1',
            'enrolled_at' => now()->subDays(10),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'assigned_at' => now()->subDays(9),
            'is_active' => true,
            'assigned_by_user_id' => $staff->id,
        ]);

        $feeStructure = FeeStructure::query()->create([
            'enrollment_id' => $enrollment->id,
            'course_fee' => 40000,
            'discount_amount' => 0,
            'net_fee' => 40000,
            'paid_amount' => 0,
            'pending_amount' => 40000,
        ]);

        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::flushValueCache();

        return [$staff, $student->fresh(), $feeStructure, $batch];
    }
}
