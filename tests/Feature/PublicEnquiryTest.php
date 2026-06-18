<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicEnquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_enquiry_creates_student_enquiry_and_visit(): void
    {
        $course = Course::query()->create([
            'name' => 'Diploma in Computer Applications',
            'code' => 'TEST-DIP',
            'course_type' => 'diploma',
            'duration' => 6,
            'duration_type' => 'months',
            'fee' => 0,
            'status' => CourseStatus::Active,
        ]);

        $response = $this->post(route('contact.enquiry'), [
            'name' => 'Rahul Sharma',
            'father_name' => 'Mr Sharma',
            'mobile' => '9876543210',
            'email' => 'rahul@example.com',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male->value,
            'course_id' => $course->id,
            'city' => 'Delhi',
            'message' => 'Interested in admission',
        ]);

        $response->assertRedirect(route('contact'));
        $response->assertSessionHas('enquiry_success');

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('enquiries', 1);
        $this->assertDatabaseCount('visits', 1);

        $enquiry = Enquiry::query()->first();
        $this->assertStringStartsWith('CRM-ENQ-', $enquiry->enquiry_number);

        $student = Student::query()->first();
        $this->assertSame('9876543210', $student->mobile);
        $this->assertNotNull($student->portal_password);
    }

    public function test_existing_mobile_reuses_student_record(): void
    {
        $course = Course::query()->create([
            'name' => 'Class 12 Science',
            'code' => 'TEST-BSC',
            'course_type' => 'bsc',
            'duration' => 3,
            'duration_type' => 'years',
            'fee' => 0,
            'status' => CourseStatus::Active,
        ]);

        $payload = [
            'name' => 'Priya Singh',
            'father_name' => 'Mr Singh',
            'mobile' => '9123456789',
            'date_of_birth' => '1999-01-10',
            'gender' => Gender::Female->value,
            'course_id' => $course->id,
        ];

        $this->post(route('contact.enquiry'), $payload);
        $this->post(route('contact.enquiry'), $payload);

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('enquiries', 2);
    }
}
