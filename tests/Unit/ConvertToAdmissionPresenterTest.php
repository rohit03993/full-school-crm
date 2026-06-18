<?php

namespace Tests\Unit;

use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Student;
use App\Services\ConvertToAdmissionPresenter;
use App\Support\DefaultCourse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConvertToAdmissionPresenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_convertible_enquiries_are_sorted_latest_first(): void
    {
        $student = $this->createStudent();
        $course = $this->createCourse('BSc', 'BSC-01');
        $undecided = DefaultCourse::undecided();

        $older = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-2026-000001',
            'course_id' => $course->id,
            'lead_source' => LeadSource::Website,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
            'created_at' => now()->subDay(),
        ]);

        $latest = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-2026-000002',
            'course_id' => $undecided->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'english_coffee',
            'visit_type' => 'follow_up',
            'latest_visit_status' => 'interested',
            'created_at' => now(),
        ]);

        $convertible = app(ConvertToAdmissionPresenter::class)->convertibleEnquiries($student->fresh());

        $this->assertTrue($convertible->first()->is($latest));
        $this->assertTrue($convertible->last()->is($older));
    }

    public function test_warns_when_latest_enquiry_has_no_course_but_older_has_course(): void
    {
        $student = $this->createStudent();
        $course = $this->createCourse('Class 12 Science', 'SCH-12-SCI');
        $undecided = DefaultCourse::undecided();

        Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-2026-000001',
            'course_id' => $course->id,
            'lead_source' => LeadSource::Website,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
            'created_at' => now()->subDay(),
        ]);

        Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-2026-000002',
            'course_id' => $undecided->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'english_coffee',
            'visit_type' => 'follow_up',
            'latest_visit_status' => 'interested',
            'created_at' => now(),
        ]);

        $presenter = app(ConvertToAdmissionPresenter::class);
        $convertible = $presenter->convertibleEnquiries($student->fresh());

        $warning = $presenter->selectionWarning($convertible);

        $this->assertNotNull($warning);
        $this->assertStringContainsString('CRM-ENQ-2026-000001', $warning);
        $this->assertStringContainsString('Class 12 Science', $warning);
        $this->assertSame($convertible->first()->id, $presenter->defaultEnquiryId($convertible));
    }

    public function test_enquiry_option_labels_include_latest_marker(): void
    {
        $student = $this->createStudent();
        $course = $this->createCourse('Diploma', 'DIP-01');

        Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-2026-000010',
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $presenter = app(ConvertToAdmissionPresenter::class);
        $convertible = $presenter->convertibleEnquiries($student->fresh());
        $options = $presenter->enquiryOptions($convertible);

        $this->assertStringContainsString('Latest', reset($options));
        $this->assertStringContainsString('Walk-in', reset($options));
    }

    protected function createStudent(): Student
    {
        return Student::query()->create([
            'name' => 'Test Student',
            'mobile' => '9876543210',
            'status' => StudentStatus::Enquiry,
        ]);
    }

    protected function createCourse(string $name, string $code): Course
    {
        return Course::query()->create([
            'name' => $name,
            'code' => $code,
            'course_type' => 'diploma',
            'duration' => 6,
            'duration_type' => 'months',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);
    }
}
