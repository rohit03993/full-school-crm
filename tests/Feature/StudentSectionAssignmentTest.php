<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Enums\PaymentMode;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Resources\Students\StudentResource;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\BatchService;
use App\Services\StudentProfileDeleteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentSectionAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_directory_filter_finds_students_with_no_section(): void
    {
        $withSection = $this->createEnrolledStudent('With Section', '9876500101', '701');
        $withoutSection = $this->createEnrolledStudent('No Section', '9876500102', '702');
        $course = Course::query()->where('code', 'CLS-SEC')->firstOrFail();
        $session = AcademicSession::query()->where('code', '2026-27-sec')->firstOrFail();
        $staff = User::factory()->create(['is_active' => true]);
        $batch = Batch::query()->create([
            'name' => 'Class 11-A',
            'section' => 'A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);
        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $withSection->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $staff->id,
        ]);

        $unassignedIds = StudentResource::getEloquentQuery()
            ->whereDoesntHave('activeBatchStudent')
            ->pluck('id')
            ->all();

        $this->assertContains($withoutSection->id, $unassignedIds);
        $this->assertNotContains($withSection->id, $unassignedIds);
    }

    public function test_bulk_assign_skips_students_from_a_different_class(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole(RoleName::SuperAdmin->value);

        $match = $this->createEnrolledStudent('Match Class', '9876500201', '801');
        $other = $this->createEnrolledStudent('Other Class', '9876500202', '802', 'Class 12', 'CLS-12');
        $course11 = Course::query()->where('code', 'CLS-SEC')->firstOrFail();
        $session = AcademicSession::query()->where('code', '2026-27-sec')->firstOrFail();
        $batch = Batch::query()->create([
            'name' => 'Class 11-B',
            'section' => 'B',
            'course_id' => $course11->id,
            'academic_session_id' => $session->id,
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);

        $result = app(BatchService::class)->bulkAssignSkippingMismatches(
            $batch,
            [$match->id, $other->id],
            $staff,
        );

        $this->assertSame(1, $result['assigned']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame($batch->id, $match->fresh()->activeBatchStudent?->batch_id);
        $this->assertNull($other->fresh()->activeBatchStudent);
    }

    public function test_students_with_payments_are_not_flagged_as_safe_to_delete(): void
    {
        $student = $this->createEnrolledStudent('Paid Student', '9876500301', '901');
        $staff = User::factory()->create(['is_active' => true]);
        $enrollment = $student->activeEnrollment;
        $feeStructure = FeeStructure::query()->create([
            'enrollment_id' => $enrollment->id,
            'course_fee' => 40000,
            'discount_amount' => 0,
            'net_fee' => 40000,
            'paid_amount' => 1000,
            'pending_amount' => 39000,
        ]);

        Payment::query()->create([
            'fee_structure_id' => $feeStructure->id,
            'student_id' => $student->id,
            'payment_date' => now()->toDateString(),
            'amount' => 1000,
            'payment_mode' => PaymentMode::Cash,
            'receipt_number' => 'RCP-SEC-0001',
            'proof_image_path' => 'proofs/test.jpg',
            'added_by_user_id' => $staff->id,
        ]);

        $this->assertTrue(app(StudentProfileDeleteService::class)->hasProtectedHistory($student));
    }

    public function test_students_without_payments_or_attendance_are_safe_to_delete(): void
    {
        $student = $this->createEnrolledStudent('Import Mistake', '9876500302', '902');

        $this->assertFalse(app(StudentProfileDeleteService::class)->hasProtectedHistory($student));
    }

    protected function createEnrolledStudent(
        string $name,
        string $mobile,
        string $roll,
        string $courseName = 'Class 11 Science',
        string $courseCode = 'CLS-SEC',
    ): Student {
        $session = AcademicSession::query()->firstOrCreate(
            ['code' => '2026-27-sec'],
            [
                'name' => '2026–27',
                'starts_on' => '2026-04-01',
                'ends_on' => '2027-03-31',
                'is_current' => true,
                'is_active' => true,
            ],
        );
        $course = Course::query()->firstOrCreate(
            ['code' => $courseCode],
            [
                'name' => $courseName,
                'programme_category' => 'school',
                'duration' => 1,
                'duration_type' => 'years',
                'fee' => 40000,
                'status' => CourseStatus::Active,
            ],
        );
        $student = Student::query()->create([
            'name' => $name,
            'mobile' => $mobile,
            'status' => StudentStatus::Enrolled,
        ]);
        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'ENQ-SEC-'.$roll,
            'course_id' => $course->id,
            'lead_source' => LeadSource::BulkImport,
        ]);
        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-SEC-'.$roll,
            'course_fee' => 40000,
            'discount_amount' => 0,
            'net_fee' => 40000,
            'use_installment_plan' => false,
            'status' => AdmissionStatus::Approved,
            'approved_at' => now(),
            'submitted_at' => now(),
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

        return $student->fresh(['activeEnrollment']);
    }
}
