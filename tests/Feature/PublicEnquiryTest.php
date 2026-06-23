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
            'programme_category' => 'coaching',
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

    public function test_existing_mobile_reuses_student_without_creating_second_enquiry(): void
    {
        $course = Course::query()->create([
            'name' => 'Class 12 Science',
            'code' => 'TEST-BSC',
            'programme_category' => 'school',
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

        $this->post(route('contact.enquiry'), $payload)->assertRedirect(route('contact'));

        $this->post(route('contact.enquiry'), $payload)
            ->assertSessionHasErrors('mobile');

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('enquiries', 1);
    }

    public function test_public_enquiry_rejects_course_hidden_from_website(): void
    {
        $course = Course::query()->create([
            'name' => 'Internal Programme',
            'code' => 'INT-001',
            'programme_category' => 'coaching',
            'duration' => 6,
            'duration_type' => 'months',
            'fee' => 10000,
            'status' => CourseStatus::Active,
            'show_on_website' => false,
        ]);

        $this->post(route('contact.enquiry'), [
            'name' => 'Hidden Course Lead',
            'father_name' => 'Parent',
            'mobile' => '9876543211',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male->value,
            'course_id' => $course->id,
        ])->assertSessionHasErrors('course_id');

        $this->assertDatabaseCount('students', 0);
        $this->assertDatabaseCount('enquiries', 0);
    }

    public function test_public_enquiry_matches_student_by_alternate_mobile(): void
    {
        $course = Course::query()->create([
            'name' => 'Visible Programme',
            'code' => 'VIS-002',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 5000,
            'status' => CourseStatus::Active,
            'show_on_website' => true,
        ]);

        $existing = Student::query()->create([
            'name' => 'Existing Student',
            'father_name' => 'Parent',
            'mobile' => '9111111111',
            'alternate_mobile' => '9222222222',
            'status' => \App\Enums\StudentStatus::Enquiry,
        ]);

        $this->post(route('contact.enquiry'), [
            'name' => 'Existing Student',
            'father_name' => 'Parent',
            'mobile' => '9222222222',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male->value,
            'course_id' => $course->id,
        ])->assertRedirect(route('contact'));

        $this->assertDatabaseCount('students', 1);
        $this->assertSame($existing->id, Enquiry::query()->first()->student_id);
    }
}
