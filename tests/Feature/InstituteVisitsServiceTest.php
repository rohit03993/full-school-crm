<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\VisitStatus;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Models\Visit;
use App\Services\EnquiryService;
use App\Services\InstituteVisitsService;
use App\Services\VisitService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class InstituteVisitsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_visit_log_includes_enrolled_and_prospect_visits(): void
    {
        $staff = $this->createStaffUser();
        $course = $this->createCourse();
        $session = AcademicSession::query()->create([
            'name' => '2025-26',
            'code' => '2025-26',
            'starts_on' => '2025-04-01',
            'ends_on' => '2026-03-31',
            'is_current' => true,
        ]);

        $prospect = app(EnquiryService::class)->create([
            'name' => 'Prospect',
            'mobile' => '9000000601',
            'discussion_summary' => 'Walk-in',
            'visit_status' => VisitStatus::Interested->value,
            'meeting_with_user_id' => $staff->id,
        ], $staff, LeadSource::WalkIn);

        $enrolledStudent = Student::query()->create([
            'name' => 'Enrolled Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2008-01-01',
            'gender' => Gender::Male,
            'mobile' => '9000000602',
            'status' => StudentStatus::Enrolled,
            'portal_password' => bcrypt('Student@2026'),
        ]);

        $enrolledEnquiry = app(EnquiryService::class)->create([
            'name' => $enrolledStudent->name,
            'father_name' => $enrolledStudent->father_name,
            'date_of_birth' => $enrolledStudent->date_of_birth->toDateString(),
            'gender' => $enrolledStudent->gender->value,
            'mobile' => $enrolledStudent->mobile,
            'course_id' => $course->id,
            'visit_status' => VisitStatus::Interested->value,
        ], $staff, LeadSource::WalkIn);

        $admission = Admission::query()->create([
            'student_id' => $enrolledStudent->id,
            'enquiry_id' => $enrolledEnquiry->id,
            'admission_number' => 'CRM-ADM-VIS-001',
            'course_fee' => 25000,
            'discount_amount' => 0,
            'status' => AdmissionStatus::Approved->value,
            'approved_at' => now(),
            'submitted_at' => now(),
        ]);

        Enrollment::query()->create([
            'student_id' => $enrolledStudent->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'enrollment_number' => 'ROLL-VIS-001',
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled->value,
            'is_active' => true,
        ]);

        app(VisitService::class)->add($enrolledStudent, $enrolledEnquiry, [
            'visit_date' => now()->toDateString(),
            'discussion_summary' => 'Returning enrolled student visit',
            'status' => VisitStatus::FollowUpRequired->value,
        ], $staff);

        $service = app(InstituteVisitsService::class);
        $from = Carbon::today()->startOfMonth();
        $to = Carbon::today();

        $stats = $service->stats($from, $to);

        $this->assertGreaterThanOrEqual(2, $stats['total_visits']);
        $this->assertGreaterThanOrEqual(1, $stats['enrolled_visits']);
        $this->assertGreaterThanOrEqual(1, $stats['prospect_visits']);

        $enrolledOnly = $service->paginate($from, $to, 'enrolled');
        $this->assertTrue($enrolledOnly->contains(fn (Visit $visit): bool => $visit->student_id === $enrolledStudent->id));
        $this->assertFalse($enrolledOnly->contains(fn (Visit $visit): bool => $visit->student_id === $prospect->student_id));
    }

    public function test_repeat_and_first_time_visitor_counts(): void
    {
        $staff = $this->createStaffUser();

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Repeat Visitor',
            'mobile' => '9000000701',
            'discussion_summary' => 'First visit',
            'visit_status' => VisitStatus::Interested->value,
            'meeting_with_user_id' => $staff->id,
        ], $staff, LeadSource::WalkIn);

        $student = Student::query()->findOrFail($enquiry->student_id);
        $today = now()->toDateString();

        app(VisitService::class)->add($student, $enquiry, [
            'visit_date' => $today,
            'discussion_summary' => 'Second visit same day',
            'status' => VisitStatus::FollowUpRequired->value,
        ], $staff);

        $service = app(InstituteVisitsService::class);
        $from = Carbon::today();
        $to = Carbon::today();
        $stats = $service->stats($from, $to);

        $this->assertGreaterThanOrEqual(2, $stats['total_visits']);
        $this->assertSame(1, $stats['repeat_visit_students']);
        $this->assertGreaterThanOrEqual(1, $stats['first_time_visitors']);
    }

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }

    protected function createCourse(): Course
    {
        return Course::query()->create([
            'name' => 'Test Course',
            'code' => 'TST-VIS',
            'programme_category' => 'coaching',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 25000,
            'status' => CourseStatus::Active,
        ]);
    }
}
