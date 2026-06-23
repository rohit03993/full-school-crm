<?php

namespace Tests\Unit;

use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\MeetingFor;
use App\Enums\ProfilePhase;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Student;
use App\Services\StudentCounterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentCounterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_profile_shows_visit_and_enquiry_counters_only(): void
    {
        $student = Student::query()->create([
            'name' => 'Lead Person',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876543210',
            'status' => StudentStatus::Enquiry,
        ]);

        $profile = app(StudentCounterService::class)->profile($student);

        $this->assertSame(ProfilePhase::Lead, $profile['phase']);

        $labels = collect($profile['items'])->pluck('label')->all();

        $this->assertSame(['Visits', 'Enquiries', 'Website', 'Walk-in', 'Enquiry', 'Admission', 'Marketing', 'Fees', 'General'], $labels);
        $this->assertNotContains('Attendance', $labels);
        $this->assertNotContains('Paid', $labels);
    }

    public function test_lead_source_summary_tracks_website_and_walk_in_enquiries(): void
    {
        $student = Student::query()->create([
            'name' => 'Mixed Lead',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876543212',
            'status' => StudentStatus::Enquiry,
        ]);

        $course = Course::query()->create([
            'name' => 'Diploma',
            'code' => 'DIP-SRC',
            'programme_category' => 'coaching',
            'duration' => 6,
            'duration_type' => 'months',
            'fee' => 0,
            'status' => CourseStatus::Active,
        ]);

        Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-2026-000101',
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => MeetingFor::Marketing,
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
            'created_at' => now(),
        ]);

        $profile = app(StudentCounterService::class)->profile($student->fresh());

        $this->assertSame(0, $profile['lead_sources']['website_count']);
        $this->assertSame(1, $profile['lead_sources']['walk_in_count']);
        $this->assertSame(1, $profile['lead_sources']['meeting_for_counts']['marketing'] ?? 0);
        $this->assertSame('Walk-in lead', $profile['lead_sources']['headline']);
        $this->assertSame('Walk-in for Marketing', $profile['lead_sources']['latest_intent']);
    }

    public function test_admission_in_progress_uses_admission_counters(): void
    {
        $student = Student::query()->create([
            'name' => 'Applicant',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876543211',
            'status' => StudentStatus::AdmissionSubmitted,
        ]);

        $profile = app(StudentCounterService::class)->profile($student);

        $this->assertSame(ProfilePhase::Admission, $profile['phase']);
        $this->assertContains('Admission', collect($profile['items'])->pluck('label')->all());
    }
}
