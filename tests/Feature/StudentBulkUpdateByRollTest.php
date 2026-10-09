<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentBulkImportService;
use App\Support\StudentImportFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentBulkUpdateByRollTest extends TestCase
{
    use RefreshDatabase;

    public function test_roll_number_update_writes_only_the_filled_columns(): void
    {
        $staff = User::factory()->create();
        $student = $this->enrolledStudent('ROLL-21', 'Aman Kumar', 'Old address');

        $service = app(StudentBulkImportService::class);
        $preview = $service->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::ADDRESS,
                2 => StudentImportFields::CITY,
            ],
            [
                ['ROLL-21', '', 'Agra'],
                ['MISSING-9', 'Nowhere', 'Agra'],
            ],
            null,
            null,
            true,
        );

        $this->assertSame('ready', $preview[0]['status']);
        $this->assertSame([], $preview[0]['warnings']);
        $this->assertTrue($preview[0]['update_only']);
        $this->assertSame($student->id, $preview[0]['existing_student']['id']);
        $this->assertSame('error', $preview[1]['status']);
        $this->assertSame(1, $service->countImportableRows($preview, []));

        $result = $service->import($staff, 'addresses.xlsx', $preview, []);

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(1, Student::query()->count());

        $student->refresh();
        $this->assertSame('Old address', $student->address);
        $this->assertSame('Agra', $student->city);
        $this->assertSame('Aman Kumar', $student->name);
        $this->assertSame('Parent', $student->father_name);
        $this->assertSame('ROLL-21', $student->activeEnrollment?->enrollment_number);
    }

    public function test_new_student_import_does_not_auto_map_address_columns(): void
    {
        $mapper = app(\App\Services\StudentImportColumnMapper::class);

        $normalImport = $mapper->guess(['Roll No', 'Student Name', 'Address', 'City']);
        $this->assertSame(StudentImportFields::SKIP, $normalImport[2]);
        $this->assertSame(StudentImportFields::SKIP, $normalImport[3]);

        $updateImport = $mapper->guess(['Roll No', 'Student Name', 'Address', 'City'], true);
        $this->assertSame(StudentImportFields::ADDRESS, $updateImport[2]);
        $this->assertSame(StudentImportFields::CITY, $updateImport[3]);
    }

    private function enrolledStudent(string $roll, string $name, string $address): Student
    {
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
            'code' => 'C10',
            'programme_category' => 'coaching',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 1000,
            'status' => CourseStatus::Active,
        ]);

        $student = Student::query()->create([
            'name' => $name,
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876500091',
            'address' => $address,
            'status' => StudentStatus::Enrolled,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'ENQ-ROLL-21',
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'joined',
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-ROLL-21',
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

        return $student;
    }
}
