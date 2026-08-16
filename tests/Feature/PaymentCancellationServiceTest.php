<?php

namespace Tests\Feature;

use App\Enums\AccountingReferenceType;
use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\PaymentCancellationRequestStatus;
use App\Enums\PaymentMode;
use App\Enums\PaymentShortfallAction;
use App\Enums\PaymentStatus;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\AccountingJournalEntry;
use App\Models\Course;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\EnquiryService;
use App\Services\FeeMiscChargeService;
use App\Services\FeesDashboardService;
use App\Services\PaymentCancellationService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentCancellationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_request_cancel_of_latest_tuition_payment_and_admin_approves(): void
    {
        Storage::fake('local');

        $staff = $this->createStaff();
        $admin = $this->createSuperAdmin();
        [$student, $feeStructure] = $this->createEnrolledStudent($staff);

        $payment = app(PaymentService::class)->add(
            $feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 10000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-CANCEL-1',
                'shortfall_action' => PaymentShortfallAction::NewInstallment->value,
                'shortfall_due_date' => now()->addMonth()->toDateString(),
                'shortfall_label' => 'Balance',
            ],
            UploadedFile::fake()->image('voucher.jpg'),
            $staff,
        );

        $feeStructure->refresh();
        $this->assertSame(10000.0, (float) $feeStructure->paid_amount);
        $this->assertSame(40000.0, (float) $feeStructure->pending_amount);
        $this->assertNotEmpty($payment->allocation_snapshot['applications'] ?? []);

        $cancellations = app(PaymentCancellationService::class);
        $request = $cancellations->submitRequest($payment, $staff, 'Wrong amount entered');

        $this->assertTrue($request->isPending());
        $this->assertTrue($cancellations->hasPendingRequest($payment));

        $cancellations->approve($request->fresh(['payment']), $admin, 'Verified mistake');

        $payment = $payment->fresh();
        $feeStructure = $feeStructure->fresh('installments');

        $this->assertSame(PaymentStatus::Cancelled, $payment->status);
        $this->assertSame(PaymentCancellationRequestStatus::Approved, $request->fresh()->status);
        $this->assertSame(0.0, (float) $feeStructure->paid_amount);
        $this->assertSame(50000.0, (float) $feeStructure->pending_amount);
        $this->assertSame(2, $feeStructure->installments->count());
        $this->assertTrue($payment->hasReceiptPdf());

        $this->assertTrue(
            AccountingJournalEntry::query()
                ->where('reference_type', AccountingReferenceType::PaymentCancellation)
                ->where('reference_id', $payment->id)
                ->exists()
        );
    }

    public function test_cannot_cancel_older_payment_when_newer_exists(): void
    {
        Storage::fake('local');

        $staff = $this->createStaff();
        [$student, $feeStructure] = $this->createEnrolledStudent($staff);
        $payments = app(PaymentService::class);

        $first = $payments->add(
            $feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 25000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-OLD',
            ],
            UploadedFile::fake()->image('a.jpg'),
            $staff,
        );

        $payments->add(
            $feeStructure->fresh(),
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 5000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-NEW',
                'shortfall_action' => PaymentShortfallAction::NewInstallment->value,
                'shortfall_due_date' => now()->addDays(20)->toDateString(),
                'shortfall_label' => 'Later balance',
            ],
            UploadedFile::fake()->image('b.jpg'),
            $staff,
        );

        $this->expectException(ValidationException::class);

        app(PaymentCancellationService::class)->submitRequest(
            $first->fresh(),
            $staff,
            'Trying to cancel older payment',
        );
    }

    public function test_second_pending_request_is_blocked(): void
    {
        Storage::fake('local');

        $staff = $this->createStaff();
        [$student, $feeStructure] = $this->createEnrolledStudent($staff);

        $payment = app(PaymentService::class)->add(
            $feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 5000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-DUP',
                'shortfall_action' => PaymentShortfallAction::CarryForward->value,
            ],
            UploadedFile::fake()->image('dup.jpg'),
            $staff,
        );

        $cancellations = app(PaymentCancellationService::class);
        $cancellations->submitRequest($payment, $staff, 'First request');

        $this->expectException(ValidationException::class);
        $cancellations->submitRequest($payment->fresh(), $staff, 'Second request');
    }

    public function test_non_super_admin_cannot_approve(): void
    {
        Storage::fake('local');

        $staff = $this->createStaff();
        [$student, $feeStructure] = $this->createEnrolledStudent($staff);

        $payment = app(PaymentService::class)->add(
            $feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 5000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-PERM',
                'shortfall_action' => PaymentShortfallAction::CarryForward->value,
            ],
            UploadedFile::fake()->image('perm.jpg'),
            $staff,
        );

        $cancellations = app(PaymentCancellationService::class);
        $request = $cancellations->submitRequest($payment, $staff, 'Need cancel');

        $this->expectException(ValidationException::class);
        $cancellations->approve($request, $staff);
    }

    public function test_misc_payment_cancel_restores_charge_pending(): void
    {
        Storage::fake('local');

        $staff = $this->createStaff();
        $admin = $this->createSuperAdmin();
        [$student, $feeStructure] = $this->createEnrolledStudent($staff);

        $charge = app(FeeMiscChargeService::class)->addSeparateCharge(
            $feeStructure,
            'Exam fee',
            2000,
            null,
            $staff,
        );

        $payment = app(PaymentService::class)->addMisc(
            $feeStructure,
            $student,
            $charge,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 2000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-MISC',
            ],
            UploadedFile::fake()->image('misc.jpg'),
            $staff,
        );

        $this->assertSame(0.0, $charge->fresh()->pendingAmount());

        $cancellations = app(PaymentCancellationService::class);
        $request = $cancellations->submitRequest($payment, $staff, 'Misc paid by mistake');
        $cancellations->approve($request->fresh(['payment']), $admin);

        $this->assertSame(PaymentStatus::Cancelled, $payment->fresh()->status);
        $this->assertSame(2000.0, $charge->fresh()->pendingAmount());
    }

    public function test_summary_and_history_include_approved_and_rejected_cancels(): void
    {
        Storage::fake('local');

        $staff = $this->createStaff();
        $admin = $this->createSuperAdmin();
        [$student, $feeStructure] = $this->createEnrolledStudent($staff);
        $payments = app(PaymentService::class);
        $cancellations = app(PaymentCancellationService::class);

        $first = $payments->add(
            $feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 8000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-HIST-1',
                'shortfall_action' => PaymentShortfallAction::CarryForward->value,
            ],
            UploadedFile::fake()->image('hist1.jpg'),
            $staff,
        );

        $approvedRequest = $cancellations->submitRequest($first, $staff, 'Wrong receipt');
        $cancellations->approve($approvedRequest->fresh(['payment']), $admin, 'Confirmed');

        $second = $payments->add(
            $feeStructure->fresh(),
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 5000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-HIST-2',
                'shortfall_action' => PaymentShortfallAction::CarryForward->value,
            ],
            UploadedFile::fake()->image('hist2.jpg'),
            $staff,
        );

        $rejectedRequest = $cancellations->submitRequest($second, $staff, 'Changed mind');
        $cancellations->reject($rejectedRequest, $admin, 'Keep payment');

        $summary = $cancellations->summary();
        $history = $cancellations->recentHistory();

        $this->assertSame(0, $summary['pending_count']);
        $this->assertSame(1, $summary['approved_count']);
        $this->assertSame(8000.0, $summary['approved_total']);
        $this->assertSame(1, $summary['rejected_count']);
        $this->assertSame(2, $summary['reviewed_count']);
        $this->assertCount(2, $history);
        $this->assertTrue($history->contains(
            fn ($row) => $row->status === PaymentCancellationRequestStatus::Approved
                && $row->payment?->receipt_number === $first->receipt_number
        ));
        $this->assertTrue($history->contains(
            fn ($row) => $row->status === PaymentCancellationRequestStatus::Rejected
                && $row->payment?->receipt_number === $second->receipt_number
        ));
    }

    public function test_collection_kpis_ignore_cancelled_payments(): void
    {
        Storage::fake('local');

        $staff = $this->createStaff();
        $admin = $this->createSuperAdmin();
        [$student, $feeStructure] = $this->createEnrolledStudent($staff);

        $payment = app(PaymentService::class)->add(
            $feeStructure,
            $student,
            [
                'payment_date' => now()->toDateString(),
                'amount' => 8000,
                'payment_mode' => PaymentMode::Cash->value,
                'voucher_number' => 'VCH-KPI',
                'shortfall_action' => PaymentShortfallAction::CarryForward->value,
            ],
            UploadedFile::fake()->image('kpi.jpg'),
            $staff,
        );

        $before = app(FeesDashboardService::class)->summary();
        $this->assertGreaterThanOrEqual(8000.0, $before['collection_today']);

        $cancellations = app(PaymentCancellationService::class);
        $request = $cancellations->submitRequest($payment, $staff, 'KPI cancel');
        $cancellations->approve($request->fresh(['payment']), $admin);

        $after = app(FeesDashboardService::class)->summary();
        $this->assertSame(
            round($before['collection_today'] - 8000, 2),
            round($after['collection_today'], 2),
        );
        $this->assertSame(0, Payment::query()->active()->whereKey($payment->id)->count());
    }

    /**
     * @return array{0: Student, 1: \App\Models\FeeStructure}
     */
    protected function createEnrolledStudent(User $staff): array
    {
        $student = Student::query()->create([
            'name' => 'Cancel Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => '9876543299',
            'status' => StudentStatus::Enquiry,
            'portal_password' => app(\App\Services\StudentAuthService::class)->hashPortalPassword('15052000'),
        ]);

        $course = Course::query()->create([
            'name' => 'Cancel Course',
            'code' => 'CAN-101',
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

        $admissionService = app(AdmissionService::class);
        $admission = $admissionService->convert($student, $enquiry, $staff, [
            'course_id' => $course->id,
            'use_installment_plan' => true,
            'installment_plan' => [
                ['label' => 'Installment 1', 'amount' => 25000, 'due_date' => now()->toDateString()],
                ['label' => 'Installment 2', 'amount' => 25000, 'due_date' => now()->addMonth()->toDateString()],
            ],
        ]);

        $admission = $admissionService->submitForm(
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

        $enrollment = $admissionService->approve($admission, $staff);

        return [$student->fresh(), $enrollment->feeStructure];
    }

    protected function createStaff(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::Staff->value);
        $user->givePermissionTo([
            \App\Enums\CrmPermission::FeesCollect->value,
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
}
