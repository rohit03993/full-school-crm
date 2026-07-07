<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Enums\StudentStatus;
use App\Enums\VisitStatus;
use App\Filament\Resources\Enquiries\EnquiryResource;
use App\Filament\Resources\Students\StudentResource;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadsStudentsListSeparationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_leads_excludes_enrolled_and_joined_enquiries(): void
    {
        $course = $this->createCourse();
        $prospect = $this->createEnquiry('Prospect Lead', '9000001001', VisitStatus::Interested, $course);
        $joinedOnly = $this->createEnquiry('Joined Lead', '9000001002', VisitStatus::Joined, $course);
        $enrolled = $this->createEnquiry('Enrolled Lead', '9000001003', VisitStatus::Joined, $course);
        $this->attachEnrollment($enrolled->student, $enrolled, $course, '101');

        $leadIds = EnquiryResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($prospect->id, $leadIds);
        $this->assertNotContains($joinedOnly->id, $leadIds);
        $this->assertNotContains($enrolled->id, $leadIds);
    }

    public function test_all_students_excludes_enquiry_stage_people(): void
    {
        $course = $this->createCourse();
        $enquiryOnly = Student::query()->create([
            'name' => 'Enquiry Only',
            'mobile' => '9000002001',
            'status' => StudentStatus::Enquiry,
        ]);
        Enquiry::query()->create([
            'student_id' => $enquiryOnly->id,
            'enquiry_number' => 'ENQ-ONLY-001',
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'latest_visit_status' => VisitStatus::Interested,
        ]);

        $enrolled = $this->createEnrolledStudent('Enrolled Student', '9000002002', '202');

        $studentIds = StudentResource::getEloquentQuery()->pluck('id')->all();

        $this->assertNotContains($enquiryOnly->id, $studentIds);
        $this->assertContains($enrolled->id, $studentIds);
    }

    protected function createCourse(?string $code = null): Course
    {
        return Course::query()->create([
            'name' => 'Class 10',
            'code' => $code ?? 'CLS-'.strtoupper(substr(uniqid(), -8)),
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);
    }

    protected function createEnquiry(string $name, string $mobile, VisitStatus $status, ?Course $course = null): Enquiry
    {
        $course ??= $this->createCourse();

        $student = Student::query()->create([
            'name' => $name,
            'mobile' => $mobile,
            'status' => StudentStatus::Enquiry,
        ]);

        return Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'ENQ-'.$mobile,
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'latest_visit_status' => $status,
        ]);
    }

    protected function attachEnrollment(Student $student, Enquiry $enquiry, Course $course, string $roll): void
    {
        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27-'.strtoupper(substr(uniqid(), -6)),
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $student->update(['status' => StudentStatus::Enrolled]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-'.$roll,
            'course_fee' => 50000,
            'discount_amount' => 0,
            'net_fee' => 50000,
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
    }

    protected function createEnrolledStudent(string $name, string $mobile, string $roll): Student
    {
        $course = $this->createCourse();
        $enquiry = $this->createEnquiry($name, $mobile, VisitStatus::Joined, $course);
        $this->attachEnrollment($enquiry->student, $enquiry, $course, $roll);

        return $enquiry->student->fresh(['activeEnrollment']);
    }
}
