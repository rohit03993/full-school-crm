<?php

namespace Tests\Feature;

use App\Filament\Forms\AdjustFeeStructureFormSchema;
use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\FeeStructureHistory;
use App\Models\Student;
use App\Models\User;
use App\Services\AdmissionService;
use App\Services\EnquiryService;
use App\Services\FeeStructureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FeeStructureHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_fee_change_writes_history_and_recalculates_pending(): void
    {
        Storage::fake('local');

        $admin = $this->createAdminUser();
        $staff = $this->createStaffUser();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        $updated = app(FeeStructureService::class)->updateByAdmin($feeStructure, [
            'course_fee' => 50000,
            'discount_amount' => 10000,
            'reason' => 'Scholarship approved by director',
        ], $admin);

        $this->assertSame(40000.0, (float) $updated->net_fee);
        $this->assertSame(40000.0, (float) $updated->pending_amount);

        $this->assertDatabaseHas('fee_structure_history', [
            'fee_structure_id' => $feeStructure->id,
            'old_net_fee' => 50000,
            'new_net_fee' => 40000,
            'reason' => 'Scholarship approved by director',
        ]);

        $this->assertSame(1, FeeStructureHistory::query()->count());
    }

    public function test_admin_can_reschedule_remaining_installments_after_fee_change(): void
    {
        Storage::fake('local');

        $admin = $this->createAdminUser();
        $staff = $this->createStaffUser();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        $updated = app(FeeStructureService::class)->updateByAdmin($feeStructure, [
            'course_fee' => 50000,
            'discount_amount' => 10000,
            'reason' => 'Revised scholarship and installment plan',
            'reschedule_installments' => true,
            'installment_plan' => [
                ['label' => 'Term 1', 'amount' => 20000, 'due_date' => now()->addMonth()->toDateString()],
                ['label' => 'Term 2', 'amount' => 20000, 'due_date' => now()->addMonths(2)->toDateString()],
            ],
        ], $admin);

        $this->assertSame(40000.0, (float) $updated->net_fee);
        $this->assertSame(40000.0, (float) $updated->pending_amount);
        $this->assertCount(2, $updated->installments);
        $this->assertSame(20000.0, (float) $updated->installments->first()->pending_amount);
    }

    public function test_installment_date_change_requires_reason_and_stores_schedule_changes(): void
    {
        Storage::fake('local');

        $admin = $this->createAdminUser();
        $staff = $this->createStaffUser();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        $oldDue = now()->addMonth()->toDateString();
        $newDue = now()->addMonths(2)->toDateString();

        app(FeeStructureService::class)->updateByAdmin($feeStructure, [
            'course_fee' => 50000,
            'discount_amount' => 0,
            'reason' => 'Initial two-part plan',
            'reschedule_installments' => true,
            'installment_plan' => [
                ['label' => 'Term 1', 'amount' => 25000, 'due_date' => $oldDue],
                ['label' => 'Term 2', 'amount' => 25000, 'due_date' => now()->addMonths(3)->toDateString()],
            ],
        ], $admin);

        $feeStructure = $feeStructure->fresh('installments');

        try {
            app(FeeStructureService::class)->updateByAdmin($feeStructure, [
                'course_fee' => 50000,
                'discount_amount' => 0,
                'reason' => '',
                'reschedule_installments' => true,
                'installment_plan' => [
                    ['label' => 'Term 1', 'amount' => 25000, 'due_date' => $newDue],
                    ['label' => 'Term 2', 'amount' => 25000, 'due_date' => now()->addMonths(3)->toDateString()],
                ],
            ], $admin);
            $this->fail('Expected ValidationException when schedule changes without reason.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }

        $updated = app(FeeStructureService::class)->updateByAdmin($feeStructure->fresh('installments'), [
            'course_fee' => 50000,
            'discount_amount' => 0,
            'reason' => 'Parent promised payment on '.$newDue,
            'reschedule_installments' => true,
            'installment_plan' => [
                ['label' => 'Term 1', 'amount' => 25000, 'due_date' => $newDue],
                ['label' => 'Term 2', 'amount' => 25000, 'due_date' => now()->addMonths(3)->toDateString()],
            ],
        ], $admin);

        $this->assertSame(50000.0, (float) $updated->net_fee);

        $history = FeeStructureHistory::query()
            ->where('fee_structure_id', $feeStructure->id)
            ->where('reason', 'Parent promised payment on '.$newDue)
            ->first();

        $this->assertNotNull($history);
        $this->assertIsArray($history->schedule_changes);
        $this->assertNotEmpty($history->schedule_changes);
        $this->assertSame($oldDue, $history->schedule_changes[0]['old_due_date']);
        $this->assertSame($newDue, $history->schedule_changes[0]['new_due_date']);
    }

    public function test_identical_reschedule_keeps_canned_reason_without_schedule_changes(): void
    {
        Storage::fake('local');

        $admin = $this->createAdminUser();
        $staff = $this->createStaffUser();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        $due1 = now()->addMonth()->toDateString();
        $due2 = now()->addMonths(2)->toDateString();

        app(FeeStructureService::class)->updateByAdmin($feeStructure, [
            'course_fee' => 50000,
            'discount_amount' => 0,
            'reason' => 'Set schedule',
            'reschedule_installments' => true,
            'installment_plan' => [
                ['label' => 'Term 1', 'amount' => 25000, 'due_date' => $due1],
                ['label' => 'Term 2', 'amount' => 25000, 'due_date' => $due2],
            ],
        ], $admin);

        $feeStructure = $feeStructure->fresh('installments');
        FeeStructureHistory::query()->delete();

        $updated = app(FeeStructureService::class)->updateByAdmin($feeStructure, [
            'course_fee' => 50000,
            'discount_amount' => 0,
            'reason' => '',
            'reschedule_installments' => true,
            'installment_plan' => [
                ['label' => 'Term 1', 'amount' => 25000, 'due_date' => $due1],
                ['label' => 'Term 2', 'amount' => 25000, 'due_date' => $due2],
            ],
        ], $admin);

        $this->assertCount(2, $updated->installments);

        $history = FeeStructureHistory::query()->where('fee_structure_id', $feeStructure->id)->first();
        $this->assertNotNull($history);
        $this->assertSame(AdjustFeeStructureFormSchema::INSTALLMENT_ONLY_REASON, $history->reason);
        $this->assertNull($history->schedule_changes);
    }

    public function test_discount_change_still_requires_reason(): void
    {
        Storage::fake('local');

        $admin = $this->createAdminUser();
        $staff = $this->createStaffUser();
        $student = $this->createEnrolledStudent($staff);
        $feeStructure = $student->activeEnrollment->feeStructure;

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(FeeStructureService::class)->updateByAdmin($feeStructure, [
            'course_fee' => 50000,
            'discount_amount' => 5000,
            'reason' => '',
        ], $admin);
    }

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createAdminUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createEnrolledStudent(User $staff): Student
    {
        $student = Student::query()->create([
            'name' => 'Fee History Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2000-05-15',
            'gender' => Gender::Male,
            'mobile' => '9876543214',
            'status' => StudentStatus::Enquiry,
            'portal_password' => app(\App\Services\StudentAuthService::class)->hashPortalPassword('15052000'),
        ]);

        $course = Course::query()->create([
            'name' => 'Diploma Fee Hist',
            'code' => 'DIP-FH',
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

        return $student->fresh(['activeEnrollment.feeStructure']);
    }
}
