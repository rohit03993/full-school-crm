<?php

namespace Tests\Unit;

use App\Enums\AdmissionStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\StudentSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentSearchServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_student_by_mobile(): void
    {
        $student = $this->createStudent('Rahul Sharma', '9876543210');

        $result = app(StudentSearchService::class)->search('9876543210', null, null);

        $this->assertSame(StudentSearchService::OUTCOME_FOUND, $result['outcome']);
        $this->assertTrue($result['student']->is($student));
    }

    public function test_returns_multiple_matches_for_partial_name(): void
    {
        $this->createStudent('Rahul Sharma', '9876543210');
        $this->createStudent('Rahul Verma', '9876543211');

        $result = app(StudentSearchService::class)->search(null, 'Rahul', null);

        $this->assertSame(StudentSearchService::OUTCOME_MULTIPLE, $result['outcome']);
        $this->assertCount(2, $result['students']);
    }

    public function test_returns_not_found_for_unknown_mobile(): void
    {
        $result = app(StudentSearchService::class)->search('9000000000', null, null);

        $this->assertSame(StudentSearchService::OUTCOME_NOT_FOUND, $result['outcome']);
        $this->assertNull($result['student']);
    }

    public function test_finds_student_by_enquiry_number(): void
    {
        $student = $this->createStudent('Amit Kumar', '9988776655');
        $course = Course::query()->create([
            'name' => 'Diploma',
            'code' => 'DIP-TEST',
            'programme_category' => 'coaching',
            'duration' => 6,
            'duration_type' => 'months',
            'fee' => 0,
            'status' => CourseStatus::Active,
        ]);

        Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-2026-000099',
            'course_id' => $course->id,
            'lead_source' => LeadSource::Website,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $result = app(StudentSearchService::class)->search(null, null, null, 'CRM-ENQ-2026-000099');

        $this->assertSame(StudentSearchService::OUTCOME_FOUND, $result['outcome']);
        $this->assertTrue($result['student']->is($student));
    }

    public function test_finds_student_by_alternate_mobile(): void
    {
        $student = Student::query()->create([
            'name' => 'Priya Singh',
            'father_name' => 'Parent',
            'mobile' => '9111111111',
            'alternate_mobile' => '9222222222',
            'status' => StudentStatus::Enquiry,
        ]);

        $result = app(StudentSearchService::class)->search('9222222222', null, null);

        $this->assertSame(StudentSearchService::OUTCOME_FOUND, $result['outcome']);
        $this->assertTrue($result['student']->is($student));
    }

    public function test_quick_search_finds_a_lead_by_name_and_a_student_by_roll(): void
    {
        $lead = $this->createStudent('Neha Lead', '9000000001');
        $enrolled = Student::query()->create([
            'name' => 'Arjun Student',
            'father_name' => 'Parent',
            'mobile' => '9000000002',
            'status' => StudentStatus::Enrolled,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 11',
            'code' => 'CLS-11-QS',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 0,
            'status' => CourseStatus::Active,
        ]);

        $session = AcademicSession::query()->create([
            'name' => '2026-2027',
            'code' => '2026-27-qs',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $enrolled->id,
            'enquiry_number' => 'CRM-ENQ-2026-004411',
            'course_id' => $course->id,
            'lead_source' => LeadSource::Website,
        ]);

        $admission = Admission::query()->create([
            'student_id' => $enrolled->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-QS-4411',
            'course_fee' => 0,
            'discount_amount' => 0,
            'net_fee' => 0,
            'use_installment_plan' => false,
            'status' => AdmissionStatus::Approved,
            'approved_at' => now(),
            'submitted_at' => now(),
        ]);

        Enrollment::query()->create([
            'student_id' => $enrolled->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'enrollment_number' => 'JEE-4411',
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        $byName = app(StudentSearchService::class)->quickSearch('Neha');
        $byRoll = app(StudentSearchService::class)->quickSearch('JEE-4411');
        $byMobile = app(StudentSearchService::class)->quickSearch('9000000002');

        $this->assertTrue($byName->contains(fn (Student $student): bool => $student->is($lead)));
        $this->assertTrue($byRoll->contains(fn (Student $student): bool => $student->is($enrolled)));
        $this->assertTrue($byMobile->contains(fn (Student $student): bool => $student->is($enrolled)));
    }

    public function test_quick_search_matches_each_name_word_so_a_last_name_typo_still_finds_the_person(): void
    {
        $student = $this->createStudent('Tanmay Agarwal', '9000000033');

        $matches = app(StudentSearchService::class)->quickSearch('TANMAY ADARWAL');

        $this->assertTrue($matches->contains(fn (Student $row): bool => $row->is($student)));
    }

    protected function createStudent(string $name, string $mobile): Student
    {
        return Student::query()->create([
            'name' => $name,
            'father_name' => 'Parent',
            'date_of_birth' => '2000-01-01',
            'gender' => Gender::Male,
            'mobile' => $mobile,
            'status' => StudentStatus::Enquiry,
        ]);
    }
}
