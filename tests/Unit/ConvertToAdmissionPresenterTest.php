<?php

namespace Tests\Unit;

use App\Enums\CourseStatus;
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

    public function test_convertible_enquiry_is_returned_for_open_lead(): void
    {
        $student = $this->createStudent();
        $course = $this->createCourse('BSc', 'BSC-01');

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-2026-000001',
            'course_id' => $course->id,
            'lead_source' => LeadSource::Website,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $convertible = app(ConvertToAdmissionPresenter::class)->convertibleEnquiries($student->fresh());

        $this->assertCount(1, $convertible);
        $this->assertTrue($convertible->first()->is($enquiry));
    }

    public function test_selection_warning_is_null_for_single_undecided_enquiry(): void
    {
        $student = $this->createStudent();
        $undecided = DefaultCourse::undecided();

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-2026-000001',
            'course_id' => $undecided->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'coaching',
            'visit_type' => 'follow_up',
            'latest_visit_status' => 'interested',
        ]);

        $presenter = app(ConvertToAdmissionPresenter::class);
        $convertible = $presenter->convertibleEnquiries($student->fresh());

        $this->assertNull($presenter->selectionWarning($convertible));
        $this->assertTrue($presenter->enquiryNeedsCourseSelection($enquiry));
        $this->assertSame($enquiry->id, $presenter->defaultEnquiryId($convertible));
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
            'programme_category' => 'coaching',
            'duration' => 6,
            'duration_type' => 'months',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);
    }
}
