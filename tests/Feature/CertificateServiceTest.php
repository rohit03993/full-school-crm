<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\CertificateType;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\VisitStatus;
use App\Filament\Pages\CertificatesPage;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\CertificateService;
use App\Services\EnquiryService;
use App\Services\LicenseService;
use App\Support\FeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CertificateServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
    }

    public function test_issues_bonafide_certificate_pdf_for_enrolled_student(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createEnrolledStudent($staff);

        $certificate = app(CertificateService::class)->issue(
            $student->fresh(['activeEnrollment.course']),
            CertificateType::Bonafide,
            $staff,
            ['remarks' => 'For bank account opening'],
        );

        $this->assertSame(CertificateType::Bonafide, $certificate->type);
        $this->assertSame($student->id, $certificate->student_id);
        $this->assertNotNull($certificate->enrollment_id);
        $this->assertTrue($certificate->hasPdf());
        $this->assertTrue(Storage::disk('local')->exists($certificate->pdf_path));
        $this->assertStringContainsString('CERT', $certificate->serial_number);
        $this->assertSame('For bank account opening', $certificate->remarks);
    }

    public function test_rejects_certificate_for_lead_without_enrollment(): void
    {
        $staff = $this->createStaffUser();
        $course = Course::query()->create([
            'name' => 'Class 12',
            'code' => 'C12-LEAD',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Lead Only',
            'mobile' => '9876500099',
            'course_id' => $course->id,
            'meeting_with_user_id' => $staff->id,
        ], $staff, LeadSource::WalkIn);

        $this->expectException(ValidationException::class);

        app(CertificateService::class)->issue(
            $enquiry->student,
            CertificateType::Character,
            $staff,
        );
    }

    public function test_certificates_page_requires_license_feature(): void
    {
        Setting::query()->whereIn('key', [
            LicenseService::PAYLOAD_KEY,
            LicenseService::SIGNATURE_KEY,
        ])->delete();
        Setting::flushValueCache();

        app(LicenseService::class)->save([
            'plan' => LicensePlan::Custom->value,
            'features' => [LicenseFeature::Attendance->value],
            'expires_at' => now()->addYear()->toDateString(),
        ]);

        $this->assertFalse(FeatureGate::enabled(LicenseFeature::Certificates));
        $this->assertFalse(CertificatesPage::canAccess());
    }

    protected function createStaffUser(string $name = 'Office Staff'): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create([
            'name' => $name,
            'is_active' => true,
        ]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createEnrolledStudent(User $staff): Student
    {
        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'C10-CERT-'.uniqid(),
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 80000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Enrolled Cert Student',
            'mobile' => '9811002211',
            'course_id' => $course->id,
            'meeting_with_user_id' => $staff->id,
            'visit_status' => VisitStatus::Interested->value,
        ], $staff, LeadSource::WalkIn);

        $student = $enquiry->student;
        $student->update([
            'father_name' => 'Father Name',
            'date_of_birth' => '2010-05-15',
            'status' => StudentStatus::Enrolled,
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-CERT-'.$student->id,
            'course_fee' => 80000,
            'discount_amount' => 0,
            'net_fee' => 80000,
            'use_installment_plan' => false,
            'status' => AdmissionStatus::Approved,
            'approved_at' => now(),
            'submitted_at' => now(),
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'enrollment_number' => 'ENR-CERT-'.$student->id,
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        return $student->fresh(['activeEnrollment.course']);
    }
}
