<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\CampusVisitPurpose;
use App\Enums\CrmPermission;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\VisitStatus;
use App\Filament\Pages\AllCasesPage;
use App\Filament\Pages\MyCasesPage;
use App\Filament\Pages\MyMeetingsPage;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Services\EnquiryService;
use App\Services\StudentCaseService;
use App\Support\CrmNavBadges;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MyCasesPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_my_work_page_shows_assigned_cases_tab(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);
        Permission::findOrCreate(CrmPermission::CasesView->value, 'web');

        [$student, $counsellor, $accountant] = $this->createEnrolledScenario();

        app(StudentCaseService::class)->open(
            $student->fresh(['activeEnrollment']),
            CampusVisitPurpose::Fees,
            'Fee discount request',
            null,
            $accountant,
            $counsellor,
            'Please review with parent.',
        );

        $accountant->givePermissionTo(CrmPermission::CasesView->value);

        $this->actingAs($accountant);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(MyMeetingsPage::class)
            ->call('switchWorkTab', 'my_cases')
            ->assertOk()
            ->assertSee('My work')
            ->assertSee('Fee discount request')
            ->assertSee($student->name);
    }

    public function test_legacy_my_cases_url_redirects_to_my_work_tab(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);
        Permission::findOrCreate(CrmPermission::CasesView->value, 'web');

        $accountant = User::factory()->create(['is_active' => true]);
        $accountant->assignRole(RoleName::Staff->value);
        $accountant->givePermissionTo(CrmPermission::CasesView->value);

        $this->actingAs($accountant);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(MyCasesPage::class)
            ->assertRedirect(MyMeetingsPage::getUrl(['tab' => 'my_cases']));
    }

    public function test_all_cases_tab_requires_view_all_permission(): void
    {
        $counsellor = User::factory()->create(['is_active' => true]);
        $counsellor->assignRole(StaffJobRole::Counsellor->value);

        $this->actingAs($counsellor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertFalse(AllCasesPage::canAccess());

        Livewire::test(MyMeetingsPage::class)
            ->assertDontSee('All cases');
    }

    public function test_transferred_case_is_hidden_from_opener_on_student_profile(): void
    {
        [$student, $counsellor, $accountant] = $this->createEnrolledScenario();
        $director = $this->createStaffUser('Director');
        $service = app(StudentCaseService::class);

        $case = $service->open(
            $student->fresh(['activeEnrollment']),
            CampusVisitPurpose::Academic,
            'Chemistry teacher issue',
            null,
            $accountant,
            $counsellor,
            'Please review with parent.',
        );

        $service->transfer(
            $case,
            $director,
            $accountant,
            'Needs academic coordinator review.',
        );

        $counsellor->givePermissionTo(CrmPermission::CasesView->value);

        $visible = $service->forStudent($student->fresh(), $counsellor);

        $this->assertCount(0, $visible);
    }

    public function test_nav_badge_counts_open_cases_for_assignee(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        [$student, $counsellor, $accountant] = $this->createEnrolledScenario();

        app(StudentCaseService::class)->open(
            $student->fresh(['activeEnrollment']),
            CampusVisitPurpose::Academic,
            'Batch change',
            null,
            $accountant,
            $counsellor,
            'Parent wants morning batch.',
        );

        $this->actingAs($accountant);

        CrmNavBadges::flushCaseBadgeCache($accountant->id);

        $this->assertSame(1, CrmNavBadges::myCasesOpen($accountant));
        $this->assertSame(1, CrmNavBadges::allCasesOpen());
    }

    /**
     * @return array{0: Student, 1: User, 2: User}
     */
    protected function createEnrolledScenario(): array
    {
        $reception = $this->createStaffUser('Reception');
        $counsellor = $this->createStaffUser('Counsellor');
        $accountant = $this->createStaffUser('Accounts');

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Case Page Student',
            'mobile' => '9000000601',
            'discussion_summary' => 'Walk-in',
            'visit_status' => VisitStatus::Interested->value,
        ], $reception, LeadSource::WalkIn);

        $student = $enquiry->student;
        $this->attachActiveEnrollment($student, $enquiry);

        return [$student->fresh(['activeEnrollment']), $counsellor, $accountant];
    }

    protected function attachActiveEnrollment(Student $student, Enquiry $enquiry): void
    {
        $course = $enquiry->course ?? Course::query()->findOrFail($enquiry->course_id);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-TEST-'.$student->id,
            'course_fee' => 40000,
            'discount_amount' => 0,
            'net_fee' => 40000,
            'use_installment_plan' => false,
            'status' => AdmissionStatus::Approved,
            'approved_at' => now(),
            'submitted_at' => now(),
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'enrollment_number' => 'ENR-'.$student->id,
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);
    }

    protected function createStaffUser(string $name): User
    {
        $user = User::factory()->create(['name' => $name, 'is_active' => true]);
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }
}
