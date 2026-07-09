<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\FeeMiscChargeAdjustmentRequestStatus;
use App\Enums\FeeMiscChargeAdjustmentType;
use App\Enums\FeeMiscChargeKind;
use App\Enums\FeeMiscChargeStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\EnquiryService;
use App\Services\FeeMiscChargeAdjustmentService;
use App\Services\FeeMiscChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeeMiscChargeAdjustmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_request_and_admin_approve_waive_off(): void
    {
        $staff = $this->createStaff();
        $admin = $this->createSuperAdmin();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        $charge = app(FeeMiscChargeService::class)->addSeparateCharge(
            $feeStructure,
            'Hostel',
            5000,
            null,
            $staff,
        );

        $adjustments = app(FeeMiscChargeAdjustmentService::class);

        $request = $adjustments->submitRequest(
            $charge,
            $staff,
            FeeMiscChargeAdjustmentType::WaiveOff,
            null,
            'Charge added by mistake',
        );

        $this->assertTrue($request->isPending());
        $this->assertTrue($adjustments->hasPendingRequest($charge->fresh()));
        $this->assertSame(5000.0, $feeStructure->fresh()->separateMiscChargesPendingTotal());

        $adjustments->approve($request->fresh(['charge']), $admin, 'Approved by principal');

        $charge = $charge->fresh();
        $this->assertSame(FeeMiscChargeStatus::Cancelled, $charge->status);
        $this->assertSame(FeeMiscChargeAdjustmentRequestStatus::Approved, $request->fresh()->status);
        $this->assertSame(0.0, $feeStructure->fresh()->separateMiscChargesPendingTotal());
    }

    public function test_staff_request_and_admin_approve_partial_discount(): void
    {
        $staff = $this->createStaff();
        $admin = $this->createSuperAdmin();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        $charge = app(FeeMiscChargeService::class)->addSeparateCharge(
            $feeStructure,
            'Glass Broke',
            2000,
            null,
            $staff,
        );

        $adjustments = app(FeeMiscChargeAdjustmentService::class);

        $request = $adjustments->submitRequest(
            $charge,
            $staff,
            FeeMiscChargeAdjustmentType::Discount,
            500,
            'Student already paid part in cash',
        );

        $adjustments->approve($request->fresh(['charge']), $admin);

        $charge = $charge->fresh();
        $this->assertSame(1500.0, (float) $charge->amount);
        $this->assertSame(FeeMiscChargeStatus::Pending, $charge->status);
        $this->assertSame(1500.0, $charge->pendingAmount());
    }

    public function test_gst_penalty_can_be_waived_via_approval_flow(): void
    {
        $staff = $this->createStaff();
        $admin = $this->createSuperAdmin();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        $charge = \App\Models\FeeMiscCharge::query()->create([
            'fee_structure_id' => $feeStructure->id,
            'label' => 'GST penalty on online overage',
            'amount' => 1800,
            'paid_amount' => 0,
            'kind' => FeeMiscChargeKind::GstPenalty,
            'status' => FeeMiscChargeStatus::Pending,
            'sort_order' => 1,
        ]);

        $adjustments = app(FeeMiscChargeAdjustmentService::class);

        $request = $adjustments->submitRequest(
            $charge,
            $staff,
            FeeMiscChargeAdjustmentType::WaiveOff,
            null,
            'One-time GST waiver approved by management',
        );

        $adjustments->approve($request->fresh(['charge']), $admin);

        $this->assertSame(FeeMiscChargeStatus::Cancelled, $charge->fresh()->status);
    }

    public function test_admin_can_reject_request_without_changing_charge(): void
    {
        $staff = $this->createStaff();
        $admin = $this->createSuperAdmin();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        $charge = app(FeeMiscChargeService::class)->addSeparateCharge(
            $feeStructure,
            'Materials',
            3000,
            null,
            $staff,
        );

        $adjustments = app(FeeMiscChargeAdjustmentService::class);

        $request = $adjustments->submitRequest(
            $charge,
            $staff,
            FeeMiscChargeAdjustmentType::Discount,
            1000,
            'Financial hardship',
        );

        $adjustments->reject($request, $admin, 'Insufficient documentation');

        $this->assertSame(FeeMiscChargeAdjustmentRequestStatus::Rejected, $request->fresh()->status);
        $this->assertSame(3000.0, $charge->fresh()->pendingAmount());
        $this->assertFalse($adjustments->hasPendingRequest($charge->fresh()));
    }

    public function test_cannot_submit_second_pending_request_for_same_charge(): void
    {
        $staff = $this->createStaff();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        $charge = app(FeeMiscChargeService::class)->addSeparateCharge(
            $feeStructure,
            'Hostel',
            5000,
            null,
            $staff,
        );

        $adjustments = app(FeeMiscChargeAdjustmentService::class);

        $adjustments->submitRequest(
            $charge,
            $staff,
            FeeMiscChargeAdjustmentType::WaiveOff,
            null,
            'First request',
        );

        $this->expectException(ValidationException::class);

        $adjustments->submitRequest(
            $charge->fresh(),
            $staff,
            FeeMiscChargeAdjustmentType::Discount,
            500,
            'Second request',
        );
    }

    protected function createStaff(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::Staff->value);
        $user->givePermissionTo([
            \App\Enums\CrmPermission::FeesCollect->value,
            \App\Enums\CrmPermission::FeesWaivePenalty->value,
            \App\Enums\CrmPermission::FeesAdjustStructure->value,
        ]);

        return $user;
    }

    protected function createSuperAdmin(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createEnrolledStudent(User $staff): Student
    {
        $student = Student::query()->create([
            'name' => 'Fee Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => '9876543211',
            'status' => StudentStatus::Enquiry,
            'portal_password' => app(\App\Services\StudentAuthService::class)->hashPortalPassword('15052000'),
        ]);

        $course = Course::query()->create([
            'name' => 'Demo Course',
            'code' => 'FEE-101',
            'programme_category' => 'coaching',
            'duration' => 6,
            'duration_type' => 'months',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => $student->name,
            'father_name' => $student->father_name,
            'date_of_birth' => $student->date_of_birth->toDateString(),
            'gender' => $student->gender->value,
            'mobile' => $student->mobile,
            'course_id' => $course->id,
        ], $staff, LeadSource::WalkIn);

        $admissions = app(AdmissionService::class);
        $admission = $admissions->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
            'discount_amount' => 0,
        ]);

        $admission = $admissions->submitForm(
            $admission,
            ['tenth_board' => 'CBSE'],
            [
                'photo' => UploadedFile::fake()->image('photo.jpg'),
                'aadhaar' => UploadedFile::fake()->create('aadhaar.pdf', 100, 'application/pdf'),
                'marksheet' => UploadedFile::fake()->create('marksheet.pdf', 100, 'application/pdf'),
                'signature' => UploadedFile::fake()->image('sign.jpg'),
            ],
            $staff,
        );

        $admissions->approve($admission, $staff);

        return $student->fresh();
    }
}
