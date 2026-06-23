<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\EnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdmissionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_convert_submit_and_approve_creates_enrollment(): void
    {
        Storage::fake('local');

        $staff = $this->createStaffUser();
        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);
        $student = $this->createStudent();
        $course = $this->createCourse();
        $enquiry = $this->createEnquiry($student, $course, $staff);

        $admissionService = app(AdmissionService::class);

        $admission = $admissionService->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
            'discount_amount' => 5000,
        ]);

        $this->assertSame(45000.0, (float) $admission->net_fee);
        $this->assertSame($course->id, $enquiry->fresh()->course_id);
        $this->assertSame(AdmissionStatus::Submitted, $admission->status);
        $this->assertSame(StudentStatus::AdmissionSubmitted, $student->fresh()->status);

        $admission = $admissionService->submitForm(
            $admission,
            [
                'tenth_board' => 'CBSE',
                'tenth_percentage' => 85,
                'twelfth_board' => 'CBSE',
                'twelfth_percentage' => 78,
            ],
            [
                'photo' => UploadedFile::fake()->image('photo.jpg'),
                'aadhaar' => UploadedFile::fake()->create('aadhaar.pdf', 100, 'application/pdf'),
                'marksheet' => UploadedFile::fake()->create('marksheet.pdf', 100, 'application/pdf'),
                'signature' => UploadedFile::fake()->image('sign.jpg'),
            ],
            $staff,
        );

        $this->assertSame(AdmissionStatus::VerificationPending, $admission->status);
        $this->assertSame(StudentStatus::VerificationPending, $student->fresh()->status);

        $enrollment = $admissionService->approve($admission, $staff);

        $this->assertNotNull($enrollment->feeStructure);
        $this->assertSame(45000.0, (float) $enrollment->feeStructure->net_fee);
        $this->assertSame(45000.0, (float) $enrollment->feeStructure->pending_amount);
        $this->assertStringStartsWith('CRM-', $enrollment->enrollment_number);
        $this->assertSame(EnrollmentStatus::Enrolled, $enrollment->status);
        $this->assertTrue($enrollment->is_active);
        $this->assertSame($session->id, $enrollment->academic_session_id);
        $this->assertSame(StudentStatus::Enrolled, $student->fresh()->status);
    }

    public function test_enrollment_ensures_default_portal_password(): void
    {
        Storage::fake('local');

        $staff = $this->createStaffUser();
        AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $student = Student::query()->create([
            'name' => 'Portal Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => '9811223344',
            'status' => StudentStatus::Enquiry,
            'portal_password' => null,
        ]);

        $course = $this->createCourse();
        $enquiry = $this->createEnquiry($student, $course, $staff);
        $admissionService = app(AdmissionService::class);

        $admission = $admissionService->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
            'discount_amount' => 0,
        ]);

        $admission = $admissionService->submitForm(
            $admission,
            [
                'tenth_board' => 'CBSE',
                'tenth_percentage' => 85,
            ],
            [
                'photo' => UploadedFile::fake()->image('photo.jpg'),
                'aadhaar' => UploadedFile::fake()->create('aadhaar.pdf', 100, 'application/pdf'),
                'marksheet' => UploadedFile::fake()->create('marksheet.pdf', 100, 'application/pdf'),
                'signature' => UploadedFile::fake()->image('sign.jpg'),
            ],
            $staff,
        );

        $admissionService->approve($admission, $staff);

        $student->refresh();
        $this->assertNotNull($student->portal_password);
        $this->assertTrue(
            app(\App\Services\StudentAuthService::class)->login(
                '9811223344',
                config('institute.portal_default_password'),
            ) !== null,
        );
    }

    public function test_student_portal_login_with_default_password(): void
    {
        $student = $this->createStudent();

        $response = $this->post(route('portal.login.submit'), [
            'mobile' => $student->mobile,
            'password' => config('institute.portal_default_password'),
        ]);

        $response->assertRedirect(route('portal.dashboard'));
        $this->assertSame($student->id, session('student_portal_id'));
    }

    public function test_staff_can_download_admission_document(): void
    {
        Storage::fake('local');

        $staff = $this->createStaffUser();
        $this->actingAs($staff);

        $student = $this->createStudent();
        $course = $this->createCourse();
        $enquiry = $this->createEnquiry($student, $course, $staff);
        $admissionService = app(AdmissionService::class);

        $admission = $admissionService->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
            'discount_amount' => 0,
        ]);

        $admission = $admissionService->submitForm(
            $admission,
            ['tenth_board' => 'CBSE', 'tenth_percentage' => 85],
            [
                'photo' => UploadedFile::fake()->image('photo.jpg'),
                'aadhaar' => UploadedFile::fake()->create('aadhaar.pdf', 100, 'application/pdf'),
                'marksheet' => UploadedFile::fake()->create('marksheet.pdf', 100, 'application/pdf'),
                'signature' => UploadedFile::fake()->image('sign.jpg'),
            ],
            $staff,
        );

        $document = $admission->documents->first();
        $this->assertNotNull($document);

        $response = $this->get(route('admin.documents.download', $document));

        $response->assertOk();
    }

    public function test_fees_can_be_updated_on_admission_tab_before_approval(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createStudent();
        $course = $this->createCourse();
        $enquiry = $this->createEnquiry($student, $course, $staff);

        $admissionService = app(AdmissionService::class);

        $admission = $admissionService->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
            'discount_amount' => 0,
        ]);

        $admission = $admissionService->updateFees($admission, 10000, $staff);

        $this->assertSame(10000.0, (float) $admission->discount_amount);
        $this->assertSame(40000.0, (float) $admission->net_fee);
    }

    public function test_convert_cannot_run_twice_for_same_enquiry(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createStudent();
        $course = $this->createCourse();
        $enquiry = $this->createEnquiry($student, $course, $staff);
        $admissionService = app(AdmissionService::class);

        $admissionService->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
            'discount_amount' => 0,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        $admissionService->convert($student, $enquiry->fresh(), $staff, [
            'course_id' => $course->id,
            'discount_amount' => 0,
        ]);
    }

    public function test_convert_rejects_undecided_course(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createStudent();
        $undecided = \App\Support\DefaultCourse::undecided();
        $enquiry = $this->createEnquiry($student, $undecided, $staff);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(AdmissionService::class)->convert($student, $enquiry, $staff, [
            'course_id' => $undecided->id,
            'discount_amount' => 0,
        ]);
    }

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createStudent(): Student
    {
        return Student::query()->create([
            'name' => 'Test Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => '9876543210',
            'status' => StudentStatus::Enquiry,
            'portal_password' => app(\App\Services\StudentAuthService::class)->hashForNewStudent(),
        ]);
    }

    protected function createCourse(): Course
    {
        return Course::query()->create([
            'name' => 'Diploma Test',
            'code' => 'DIP-ADM',
            'programme_category' => 'coaching',
            'duration' => 6,
            'duration_type' => 'months',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);
    }

    protected function createEnquiry(Student $student, Course $course, User $staff): Enquiry
    {
        return app(EnquiryService::class)->create([
            'name' => $student->name,
            'father_name' => $student->father_name,
            'date_of_birth' => $student->date_of_birth->toDateString(),
            'gender' => $student->gender->value,
            'mobile' => $student->mobile,
            'course_id' => $course->id,
        ], $staff, LeadSource::WalkIn);
    }
}
