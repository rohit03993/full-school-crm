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
use App\Models\User;
use App\Services\StudentCounterService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentCounterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_profile_shows_visit_call_and_enquiry_counters_only(): void
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

        $this->assertSame(['Visits', 'Calls', 'Enquiries'], $labels);
        $this->assertNotContains('Website', $labels);
        $this->assertNotContains('Walk-in', $labels);
        $this->assertNotContains('Attendance', $labels);
        $this->assertNotContains('Paid', $labels);
    }

    public function test_visit_counter_excludes_phone_call_mirror_rows(): void
    {
        $student = Student::query()->create([
            'name' => 'Mirror Visit Lead',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876543290',
            'status' => StudentStatus::Enquiry,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 6',
            'code' => 'C6-MIRROR',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-2026-000301',
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'latest_visit_status' => 'interested',
        ]);

        \App\Models\Visit::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'visit_date' => today(),
            'discussion_summary' => 'First walk-in.',
            'status' => 'interested',
        ]);

        \App\Models\Visit::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'visit_date' => today(),
            'discussion_summary' => 'Second walk-in.',
            'status' => 'interested',
        ]);

        \App\Models\Visit::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'visit_date' => today(),
            'discussion_summary' => 'Legacy phone mirror.',
            'remarks' => 'Outgoing call',
            'status' => 'interested',
        ]);

        $staff = User::factory()->create(['is_active' => true]);

        \App\Models\StudentCall::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'user_id' => $staff->id,
            'call_status' => 'connected',
            'call_direction' => 'outgoing',
            'call_notes' => 'Follow-up call logged.',
            'called_at' => now(),
        ]);

        $profile = app(StudentCounterService::class)->profile($student->fresh());
        $items = collect($profile['items'])->pluck('value', 'label');

        $this->assertSame(2, $items['Visits']);
        $this->assertSame(1, $items['Calls']);
        $this->assertSame(1, $items['Enquiries']);
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

    public function test_direct_admission_source_shows_correct_headline_without_walk_in_label(): void
    {
        $student = Student::query()->create([
            'name' => 'Direct Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876543299',
            'status' => StudentStatus::Enrolled,
        ]);

        $course = Course::query()->create([
            'name' => 'IIT JEE',
            'code' => 'JEE-DIR',
            'programme_category' => 'coaching',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 155000,
            'status' => CourseStatus::Active,
        ]);

        Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-2026-000201',
            'course_id' => $course->id,
            'lead_source' => LeadSource::DirectAdmission,
            'meeting_for' => 'enquiry',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'joined',
            'created_at' => now(),
        ]);

        $profile = app(StudentCounterService::class)->profile($student->fresh());

        $this->assertSame(0, $profile['lead_sources']['walk_in_count']);
        $this->assertSame(1, $profile['lead_sources']['direct_admission_count']);
        $this->assertSame('Direct admission', $profile['lead_sources']['headline']);
        $this->assertSame('Enrolled directly by staff', $profile['lead_sources']['detail']);
        $this->assertSame('Direct admission', $profile['lead_sources']['latest_intent']);
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
