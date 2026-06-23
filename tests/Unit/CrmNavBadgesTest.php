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
use App\Support\CrmNavBadges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CrmNavBadgesTest extends TestCase
{
    use RefreshDatabase;

    public function test_admission_badge_matches_submitted_and_verification_pending(): void
    {
        Cache::flush();

        $student = Student::query()->create([
            'name' => 'Lead',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876543299',
            'status' => StudentStatus::Enquiry,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 12',
            'code' => 'CLS-12',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-2026-000299',
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'CRM-ADM-2026-000299',
            'status' => AdmissionStatus::Submitted,
        ]);

        Admission::query()->create([
            'student_id' => Student::query()->create([
                'name' => 'Lead Two',
                'father_name' => 'Parent',
                'date_of_birth' => '2000-01-02',
                'gender' => Gender::Female,
                'mobile' => '9876543298',
                'status' => StudentStatus::Enquiry,
            ])->id,
            'enquiry_id' => Enquiry::query()->create([
                'student_id' => Student::query()->where('mobile', '9876543298')->value('id'),
                'enquiry_number' => 'CRM-ENQ-2026-000298',
                'course_id' => $course->id,
                'lead_source' => LeadSource::Website,
                'meeting_for' => 'school',
                'visit_type' => 'first_visit',
                'latest_visit_status' => 'interested',
            ])->id,
            'admission_number' => 'CRM-ADM-2026-000298',
            'status' => AdmissionStatus::VerificationPending,
        ]);

        $this->assertSame(2, CrmNavBadges::admissionsPendingAction());
        $this->assertSame(1, CrmNavBadges::admissionsAwaitingApproval());
    }
}
