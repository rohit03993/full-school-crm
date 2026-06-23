<?php

namespace Tests\Unit;

use App\Enums\Gender;
use App\Enums\StudentStatus;
use App\Enums\CourseStatus;
use App\Enums\LeadSource;
use App\Models\Course;
use App\Models\Enquiry;
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
