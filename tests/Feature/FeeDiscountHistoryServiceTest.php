<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\FeeMiscChargeAdjustmentType;
use App\Enums\FeeMiscChargeKind;
use App\Enums\FeeMiscChargeStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\FeeDiscountEntry;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\EnquiryService;
use App\Services\FeeDiscountHistoryService;
use App\Services\FeeDiscountLedgerService;
use App\Services\FeeMiscChargeAdjustmentService;
use App\Services\FeeMiscChargeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeeDiscountHistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summary_includes_tuition_and_misc_adjustments(): void
    {
        $staff = $this->createStaff();
        $admin = $this->createSuperAdmin();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        app(FeeDiscountLedgerService::class)->recordFeeStructureChange(
            $feeStructure,
            60000,
            55000,
            $admin,
            'Sibling concession',
        );

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
            'Management approved hostel waiver',
        );
        $adjustments->approve($request, $admin);

        $summary = app(FeeDiscountHistoryService::class)->summary();

        $this->assertSame(1, $summary['tuition_discount_count']);
        $this->assertSame(5000.0, $summary['tuition_discount_total']);
        $this->assertSame(1, $summary['misc_waive_count']);
        $this->assertSame(5000.0, $summary['misc_waive_total']);
        $this->assertSame(2, $summary['combined_count']);
        $this->assertSame(10000.0, $summary['combined_total']);
    }

    public function test_recent_history_lists_tuition_and_misc_entries(): void
    {
        $staff = $this->createStaff();
        $admin = $this->createSuperAdmin();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        FeeDiscountEntry::query()->create([
            'admission_id' => $student->activeEnrollment->admission_id,
            'fee_structure_id' => $feeStructure->id,
            'amount' => -2000,
            'total_after' => 58000,
            'reason' => 'Early bird discount',
            'granted_by_user_id' => $admin->id,
        ]);

        $history = app(FeeDiscountHistoryService::class)->recent();

        $this->assertCount(1, $history);
        $this->assertSame('tuition_discount', $history->first()->kind);
        $this->assertSame(2000.0, $history->first()->amount);
        $this->assertSame($student->name, $history->first()->studentName);
    }

    protected function createSuperAdmin(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
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

    protected function createEnrolledStudent(User $staff): Student
    {
        $course = Course::query()->create([
            'name' => 'Class 12',
            'code' => 'HIST-12',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 60000,
            'status' => CourseStatus::Active,
        ]);

        $student = Student::query()->create([
            'name' => 'History Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '8109462950',
            'status' => StudentStatus::Enquiry,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => $student->name,
            'father_name' => $student->father_name,
            'date_of_birth' => $student->date_of_birth->toDateString(),
            'gender' => $student->gender->value,
            'mobile' => $student->mobile,
            'course_id' => $course->id,
        ], $staff, LeadSource::WalkIn);

        app(AdmissionService::class)->convert(
            $student,
            $enquiry,
            $staff,
            [
                'course_id' => $course->id,
                'discount_amount' => 0,
                'use_installment_plan' => false,
            ],
        );

        $admissions = app(AdmissionService::class);
        $admission = $student->fresh()->admission;
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

        return $student->fresh(['activeEnrollment.feeStructure']);
    }
}
