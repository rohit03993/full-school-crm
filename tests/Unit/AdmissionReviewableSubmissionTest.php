<?php

namespace Tests\Unit;

use App\Enums\AdmissionStatus;
use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\StudentStatus;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdmissionReviewableSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_submitted_at_without_documents_is_reviewable(): void
    {
        $student = Student::query()->create([
            'name' => 'Bulk Import Student',
            'father_name' => 'Parent',
            'mobile' => '9876500999',
            'gender' => Gender::Male,
            'status' => StudentStatus::Enrolled,
        ]);

        $course = Course::query()->create([
            'name' => 'Test Course',
            'code' => 'TST-001',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-TEST-1',
            'course_id' => $course->id,
            'lead_source' => LeadSource::BulkImport,
            'meeting_for' => 'self',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'joined',
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'CRM-ADM-TEST-1',
            'course_fee' => 10000,
            'discount_amount' => 0,
            'net_fee' => 10000,
            'status' => AdmissionStatus::Approved,
            'submitted_at' => now(),
        ]);

        $this->assertTrue($admission->hasReviewableSubmission());
    }
}
