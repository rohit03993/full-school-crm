<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Resources\Students\StudentResource;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AllStudentsResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolled_student_appears_in_all_students_query(): void
    {
        $student = $this->createEnrolledStudent('Listed Student', '9876500999', '501');

        $listed = StudentResource::getEloquentQuery()
            ->where('status', StudentStatus::Enrolled)
            ->where('mobile', '9876500999')
            ->first();

        $this->assertNotNull($listed);
        $this->assertSame($student->id, $listed->id);
        $this->assertSame('501', $listed->activeEnrollment?->enrollment_number);
    }

    public function test_student_search_still_finds_imported_student_by_mobile_and_roll(): void
    {
        $student = $this->createEnrolledStudent('Searchable Student', '9876500888', '602');

        $byMobile = app(StudentSearchService::class)->search('9876500888', null, null, null);
        $byRoll = app(StudentSearchService::class)->search(null, null, '602', null);

        $this->assertSame(StudentSearchService::OUTCOME_FOUND, $byMobile['outcome']);
        $this->assertSame($student->id, $byMobile['student']?->id);
        $this->assertSame(StudentSearchService::OUTCOME_FOUND, $byRoll['outcome']);
        $this->assertSame($student->id, $byRoll['student']?->id);
    }

    public function test_staff_can_access_all_students_resource(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole(RoleName::Staff->value);

        $this->actingAs($staff);

        $this->assertTrue(StudentResource::canAccess());
    }

    protected function createEnrolledStudent(string $name, string $mobile, string $roll): Student
    {
        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);
        $course = Course::query()->create([
            'name' => 'Class 11 Science',
            'code' => 'CLS-11-SCI',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 40000,
            'status' => CourseStatus::Active,
        ]);
        $student = Student::query()->create([
            'name' => $name,
            'mobile' => $mobile,
            'status' => StudentStatus::Enrolled,
        ]);
        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'ENQ-TEST-'.$roll,
            'course_id' => $course->id,
            'lead_source' => LeadSource::BulkImport,
        ]);
        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-TEST-'.$roll,
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
