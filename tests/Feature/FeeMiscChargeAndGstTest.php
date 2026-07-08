<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\FeeMiscChargeKind;
use App\Enums\FeeMiscChargeStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\PaymentMode;
use App\Enums\PaymentShortfallAction;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\EnquiryService;
use App\Services\FeeMiscChargeService;
use App\Services\OnlineAllowanceGstService;
use App\Services\PaymentService;
use App\Support\FeeSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeeMiscChargeAndGstTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_partially_pay_separate_misc_charge(): void
    {
        $staff = $this->createStaff();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        $charge = app(FeeMiscChargeService::class)->addSeparateCharge(
            $feeStructure,
            'Materials fee',
            5000,
            null,
            $staff,
        );

        $this->actingAs($staff);

        $payment = app(PaymentService::class)->addMisc(
            $feeStructure->fresh(),
            $student,
            $charge->fresh(),
            [
                'payment_date' => now()->toDateString(),
                'amount' => 2000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-200',
            ],
            UploadedFile::fake()->image('proof.jpg'),
            $staff,
        );

        $charge = $charge->fresh();

        $this->assertSame(2000.0, (float) $payment->amount);
        $this->assertSame(FeeMiscChargeStatus::Partial, $charge->status);
        $this->assertSame(2000.0, (float) $charge->paid_amount);
        $this->assertSame(3000.0, $charge->pendingAmount());
        $this->assertSame(3000.0, $feeStructure->fresh()->separateMiscChargesPendingTotal());
    }

    public function test_recent_misc_charge_summaries_group_bulk_rows(): void
    {
        $staff = $this->createStaff();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        app(FeeMiscChargeService::class)->addSeparateCharge(
            $feeStructure,
            'Exam fee',
            2500,
            null,
            $staff,
        );

        $summaries = app(FeeMiscChargeService::class)->recentSeparateChargeSummaries();

        $this->assertCount(1, $summaries);
        $this->assertSame('Exam fee', $summaries->first()['label']);
        $this->assertSame(2500.0, $summaries->first()['amount']);
        $this->assertSame(1, $summaries->first()['student_count']);
        $this->assertSame(2500.0, $summaries->first()['pending_total']);
    }

    public function test_staff_can_add_and_pay_separate_misc_charge(): void
    {
        $staff = $this->createStaff();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        $charge = app(FeeMiscChargeService::class)->addSeparateCharge(
            $feeStructure,
            'Exam fee',
            2500,
            null,
            $staff,
        );

        $this->assertSame(FeeMiscChargeKind::Separate, $charge->kind);
        $this->assertSame(FeeMiscChargeStatus::Pending, $charge->status);
        $this->assertSame(2500.0, $feeStructure->fresh()->separateMiscChargesPendingTotal());

        $this->actingAs($staff);

        $payment = app(PaymentService::class)->addMisc(
            $feeStructure->fresh(),
            $student,
            $charge->fresh(),
            [
                'payment_date' => now()->toDateString(),
                'amount' => 2500,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-100',
            ],
            UploadedFile::fake()->image('proof.jpg'),
            $staff,
        );

        $this->assertSame(2500.0, (float) $payment->amount);
        $this->assertSame(FeeMiscChargeStatus::Paid, $charge->fresh()->status);
        $this->assertSame(0.0, $feeStructure->fresh()->separateMiscChargesPendingTotal());
    }

    public function test_online_overage_creates_gst_penalty_misc_charge(): void
    {
        Setting::setValue(FeeSettings::KEY_ONLINE_ALLOWANCE_GST_ENABLED, '1', 'fees');
        Setting::setValue(FeeSettings::KEY_GST_PENALTY_PERCENTAGE, '18', 'fees');
        Setting::flushValueCache();

        $staff = $this->createStaff();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;
        $feeStructure->update([
            'planned_cash_amount' => 60000,
            'planned_online_amount' => 40000,
        ]);

        $this->actingAs($staff);

        app(PaymentService::class)->add(
            $feeStructure->fresh(),
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 45000,
                'payment_mode' => PaymentMode::Upi->value,
                'utr_number' => 'UTR1234567890',
                'shortfall_action' => PaymentShortfallAction::NewInstallment->value,
                'shortfall_due_date' => now()->addMonth()->toDateString(),
                'shortfall_label' => 'Balance',
            ],
            UploadedFile::fake()->image('proof.jpg'),
            $staff,
        );

        $gstCharge = $feeStructure->fresh()->miscCharges
            ->first(fn ($row) => $row->kind === FeeMiscChargeKind::GstPenalty);

        $this->assertNotNull($gstCharge);
        $this->assertSame(900.0, (float) $gstCharge->amount);
    }

    public function test_super_admin_can_remove_unpaid_separate_misc_charge(): void
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

        $this->assertSame(5000.0, $feeStructure->fresh()->separateMiscChargesPendingTotal());

        app(FeeMiscChargeService::class)->waiveSeparateCharge(
            $charge,
            $staff,
            'Charge added to wrong student by mistake',
        );

        $charge = $charge->fresh();

        $this->assertSame(FeeMiscChargeStatus::Cancelled, $charge->status);
        $this->assertSame(0.0, $feeStructure->fresh()->separateMiscChargesPendingTotal());
    }

    public function test_cannot_remove_misc_charge_that_has_payments(): void
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

        $this->actingAs($staff);

        app(PaymentService::class)->addMisc(
            $feeStructure->fresh(),
            $student,
            $charge->fresh(),
            [
                'payment_date' => now()->toDateString(),
                'amount' => 2000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-201',
            ],
            UploadedFile::fake()->image('proof.jpg'),
            $staff,
        );

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(FeeMiscChargeService::class)->waiveSeparateCharge(
            $charge->fresh(),
            $staff,
            'Trying to remove after partial payment',
        );
    }

    public function test_cannot_remove_gst_penalty_via_separate_misc_waiver(): void
    {
        $staff = $this->createStaff();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        $charge = \App\Models\FeeMiscCharge::query()->create([
            'fee_structure_id' => $feeStructure->id,
            'label' => 'GST penalty on online overage',
            'amount' => 900,
            'paid_amount' => 0,
            'kind' => FeeMiscChargeKind::GstPenalty,
            'status' => FeeMiscChargeStatus::Pending,
            'sort_order' => 1,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(FeeMiscChargeService::class)->waiveSeparateCharge(
            $charge,
            $staff,
            'Should not work on GST',
        );
    }

    public function test_disabling_gst_clears_splits_and_unpaid_gst_charges(): void
    {
        Setting::setValue(FeeSettings::KEY_ONLINE_ALLOWANCE_GST_ENABLED, '1', 'fees');
        Setting::flushValueCache();

        $staff = $this->createStaff();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;
        $feeStructure->update([
            'planned_cash_amount' => 60000,
            'planned_online_amount' => 40000,
        ]);

        $student->admissions()->latest()->first()?->update([
            'planned_cash_amount' => 60000,
            'planned_online_amount' => 40000,
        ]);

        \App\Models\FeeMiscCharge::query()->create([
            'fee_structure_id' => $feeStructure->id,
            'label' => 'GST penalty on online overage',
            'amount' => 900,
            'paid_amount' => 0,
            'kind' => FeeMiscChargeKind::GstPenalty,
            'status' => FeeMiscChargeStatus::Pending,
            'sort_order' => 1,
        ]);

        $result = app(OnlineAllowanceGstService::class)->cleanupWhenDisabled($staff);

        $this->assertSame(1, $result['splits_cleared']);
        $this->assertSame(1, $result['gst_charges_removed']);

        $feeStructure = $feeStructure->fresh();
        $this->assertNull($feeStructure->planned_cash_amount);
        $this->assertNull($feeStructure->planned_online_amount);
        $this->assertFalse($feeStructure->hasOnlineAllowancePlan());
        $this->assertSame(0, $feeStructure->miscCharges()->where('kind', FeeMiscChargeKind::GstPenalty)->where('status', FeeMiscChargeStatus::Pending)->count());
    }

    public function test_online_tuition_blocked_without_cash_online_split_when_gst_enabled(): void
    {
        Setting::setValue(FeeSettings::KEY_ONLINE_ALLOWANCE_GST_ENABLED, '1', 'fees');
        Setting::flushValueCache();

        $staff = $this->createStaff();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        $this->actingAs($staff);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(PaymentService::class)->add(
            $feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 10000,
                'payment_mode' => PaymentMode::Upi->value,
                'utr_number' => 'UTR-NO-SPLIT',
                'shortfall_action' => PaymentShortfallAction::NewInstallment->value,
                'shortfall_due_date' => now()->addMonth()->toDateString(),
                'shortfall_label' => 'Balance',
            ],
            UploadedFile::fake()->image('proof.jpg'),
            $staff,
        );
    }

    public function test_cash_tuition_allowed_without_cash_online_split_when_gst_enabled(): void
    {
        Setting::setValue(FeeSettings::KEY_ONLINE_ALLOWANCE_GST_ENABLED, '1', 'fees');
        Setting::flushValueCache();

        $staff = $this->createStaff();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        $this->actingAs($staff);

        $payment = app(PaymentService::class)->add(
            $feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 10000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-CASH-OK',
                'shortfall_action' => PaymentShortfallAction::NewInstallment->value,
                'shortfall_due_date' => now()->addMonth()->toDateString(),
                'shortfall_label' => 'Balance',
            ],
            UploadedFile::fake()->image('proof.jpg'),
            $staff,
        );

        $this->assertSame(10000.0, (float) $payment->amount);
        $this->assertNull(
            $feeStructure->fresh()->miscCharges->first(fn ($row) => $row->kind === FeeMiscChargeKind::GstPenalty),
        );
    }

    protected function createStaff(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createEnrolledStudent(User $staff): Student
    {
        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'MISC-10',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 100000,
            'status' => CourseStatus::Active,
        ]);

        $student = Student::query()->create([
            'name' => 'Misc Test',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '9811223344',
            'status' => StudentStatus::Enquiry,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => $student->name,
            'mobile' => $student->mobile,
            'course_id' => $course->id,
        ], $staff, LeadSource::WalkIn);

        $admissions = app(AdmissionService::class);
        $admission = $admissions->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
            'discount_amount' => 0,
            'use_installment_plan' => false,
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

        return $student->fresh(['activeEnrollment.feeStructure.miscCharges']);
    }
}
