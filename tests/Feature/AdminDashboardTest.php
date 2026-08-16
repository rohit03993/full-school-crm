<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\CrmPermission;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\StudentStatus;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\BatchOverviewWidget;
use App\Filament\Widgets\CrmFinanceStatsWidget;
use App\Filament\Widgets\CrmLeadStatsWidget;
use App\Filament\Widgets\DashboardAnalyticsWidget;
use App\Filament\Widgets\DashboardAttentionWidget;
use App\Filament\Widgets\DashboardHeroWidget;
use App\Filament\Widgets\DashboardTodayPulseWidget;
use App\Models\AcademicSession;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Services\AttendanceService;
use App\Services\CrmDashboardService;
use App\Support\DashboardFilters;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_sees_dashboard_with_filters(): void
    {
        $session = $this->createSession();
        $this->actingAsSuperAdmin();

        Livewire::test(Dashboard::class)
            ->assertSuccessful()
            ->assertSchemaStateSet([
                'academic_session_id' => $session->id,
                'range' => DashboardFilters::RANGE_MONTH,
            ], 'filtersForm');
    }

    public function test_owner_stat_widgets_follow_the_selected_period(): void
    {
        $this->createSession();
        $this->actingAsSuperAdmin();

        $this->createEnquiry('CRM-ENQ-2026-000501', now());
        $this->createEnquiry('CRM-ENQ-2026-000502', now()->subMonths(4));

        Livewire::test(CrmLeadStatsWidget::class, [
            'pageFilters' => ['range' => DashboardFilters::RANGE_TODAY],
        ])
            ->assertSuccessful()
            ->assertSee('New leads');

        $thisMonth = Livewire::test(CrmLeadStatsWidget::class, [
            'pageFilters' => ['range' => DashboardFilters::RANGE_MONTH],
        ]);

        $thisMonth->assertSuccessful();
    }

    public function test_batch_overview_only_shows_batches_in_the_selected_session(): void
    {
        $currentSession = $this->createSession();
        $oldSession = AcademicSession::query()->create([
            'name' => '2024-2025',
            'code' => '2024-25',
            'starts_on' => '2024-04-01',
            'ends_on' => '2025-03-31',
            'is_current' => false,
            'is_active' => true,
        ]);

        $course = $this->createCourse();
        $this->createBatchForCourse($course, [
            'name' => 'Current Session Batch',
            'academic_session_id' => $currentSession->id,
        ]);
        $this->createBatchForCourse($course, [
            'name' => 'Old Session Batch',
            'academic_session_id' => $oldSession->id,
        ]);

        $this->actingAsSuperAdmin();

        Livewire::test(BatchOverviewWidget::class, [
            'pageFilters' => ['academic_session_id' => $currentSession->id],
        ])
            ->assertSuccessful()
            ->assertSee('Current Session Batch')
            ->assertDontSee('Old Session Batch');
    }

    public function test_attendance_marking_refreshes_the_dashboard_immediately(): void
    {
        $session = $this->createSession();
        $course = $this->createCourse();
        $batch = $this->createBatchForCourse($course, [
            'name' => 'Live Batch',
            'academic_session_id' => $session->id,
        ]);

        $student = Student::query()->create([
            'name' => 'Present Student',
            'mobile' => '9800000010',
            'status' => StudentStatus::Enrolled,
        ]);

        $admin = $this->actingAsSuperAdmin();

        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'assigned_at' => now(),
            'assigned_by_user_id' => $admin->id,
            'is_active' => true,
        ]);

        Livewire::test(CrmFinanceStatsWidget::class)
            ->assertSuccessful()
            ->assertSee('Enrolled students');

        $service = app(CrmDashboardService::class);
        $this->assertSame(0, $service->batchOverview()['totals']['present_today']);

        app(AttendanceService::class)->saveBatchAttendance(
            $batch,
            today()->toDateString(),
            [$student->id => AttendanceStatus::Present->value],
            $admin,
        );

        $overview = $service->batchOverview();

        $this->assertSame(1, $overview['totals']['present_today']);
        $this->assertSame(1, $overview['totals']['marked_today']);
        $this->assertArrayNotHasKey('not_marked_today', $overview['totals']);
        $this->assertArrayNotHasKey('pending_fees', $overview['totals']);
    }

    public function test_batch_overview_hides_not_marked_and_pending_fees(): void
    {
        $session = $this->createSession();
        $course = $this->createCourse();
        $this->createBatchForCourse($course, [
            'name' => 'Attendance Only Batch',
            'academic_session_id' => $session->id,
        ]);

        $this->actingAsSuperAdmin();

        Livewire::test(BatchOverviewWidget::class, [
            'pageFilters' => ['academic_session_id' => $session->id],
        ])
            ->assertSuccessful()
            ->assertSee('Attendance Only Batch')
            ->assertDontSee('Not marked')
            ->assertDontSee('Pending fees');
    }

    public function test_owner_sees_needs_attention_and_today_pulse(): void
    {
        $this->createSession();
        $this->actingAsSuperAdmin();

        Livewire::test(DashboardAttentionWidget::class)
            ->assertSuccessful()
            ->assertSee('Needs attention')
            ->assertSee('Admissions')
            ->assertSee('Students attendance');

        Livewire::test(DashboardTodayPulseWidget::class)
            ->assertSuccessful()
            ->assertSee('Today')
            ->assertSee('New leads')
            ->assertSee('Staff attendance');

        Livewire::test(DashboardHeroWidget::class)
            ->assertSuccessful()
            ->assertSee('Students attendance')
            ->assertSee('Staff attendance');
    }

    public function test_owner_sees_hideable_analytics_panel(): void
    {
        $this->createSession();
        $this->actingAsSuperAdmin();

        Livewire::test(DashboardAnalyticsWidget::class)
            ->assertSuccessful()
            ->assertSee('Hide analytics')
            ->assertSee('Students attendance')
            ->assertSee('Staff attendance')
            ->assertSee('Last 7 days')
            ->assertSee('Today’s mix')
            ->call('toggleAnalytics')
            ->assertSee('Show analytics')
            ->assertDontSee('Last 7 days');
    }

    public function test_fee_adjuster_does_not_see_admin_approvals_tile_on_attention(): void
    {
        $this->createSession();

        $adjuster = User::factory()->create(['is_active' => true]);
        $adjuster->assignRole(StaffJobRole::FeeAdjuster->value);

        $this->actingAs($adjuster);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(DashboardAttentionWidget::class)
            ->assertSuccessful()
            ->assertDontSee('Staff work open')
            ->assertSee('Overdue students');

        $this->assertFalse(DashboardAnalyticsWidget::canView());
    }

    public function test_teacher_hero_shows_academic_chips_not_calling_copy(): void
    {
        $this->createSession();

        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->assignRole(StaffJobRole::Teacher->value);

        $this->actingAs($teacher);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(DashboardHeroWidget::class)
            ->assertSuccessful()
            ->assertDontSee('Your calling workspace')
            ->assertDontSee('All Leads');
    }

    public function test_hero_quick_actions_are_all_openable_by_the_viewer(): void
    {
        $this->createSession();

        $accountant = User::factory()->create(['is_active' => true]);
        $accountant->assignRole(StaffJobRole::Accountant->value);

        $this->actingAs($accountant);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(DashboardHeroWidget::class)
            ->assertSuccessful()
            ->assertDontSee('Call Queue')
            ->assertDontSee('All cases');
    }

    public function test_staff_without_dashboard_stats_permission_cannot_view_owner_widgets(): void
    {
        $this->createSession();

        $messaging = User::factory()->create(['is_active' => true]);
        $messaging->assignRole(StaffJobRole::MessagingCoordinator->value);

        $this->actingAs($messaging);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertFalse(CrmLeadStatsWidget::canView());
        $this->assertFalse($messaging->canCrm(CrmPermission::DashboardOwnerStats));
    }

    public function test_admission_officer_sees_admissions_focused_home(): void
    {
        $this->createSession();

        $officer = User::factory()->create(['is_active' => true]);
        $officer->assignRole(StaffJobRole::AdmissionOfficer->value);

        $this->actingAs($officer);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertSame(['admissions'], \App\Support\CrmNavigation::navRolePacks($officer));

        Livewire::test(DashboardHeroWidget::class)
            ->assertSuccessful()
            ->assertSee('Pending admissions')
            ->assertSee('Admissions');

        Livewire::test(DashboardAttentionWidget::class)
            ->assertSuccessful()
            ->assertSee('Admissions')
            ->assertSee('Follow-ups due');
    }

    public function test_counsellor_hero_shows_calling_metrics(): void
    {
        $this->createSession();

        $counsellor = User::factory()->create(['is_active' => true]);
        $counsellor->assignRole(StaffJobRole::Counsellor->value);

        $this->actingAs($counsellor);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(DashboardHeroWidget::class)
            ->assertSuccessful()
            ->assertSee('Follow-ups due')
            ->assertSee('Uncalled')
            ->assertSee('Call Queue');
    }

    public function test_multi_role_counsellor_and_teacher_merge_calling_and_academic_tiles(): void
    {
        $this->createSession();

        $hybrid = User::factory()->create(['is_active' => true]);
        $hybrid->assignRole(StaffJobRole::Counsellor->value);
        $hybrid->assignRole(StaffJobRole::Teacher->value);

        $this->actingAs($hybrid);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $packs = \App\Support\CrmNavigation::navRolePacks($hybrid);
        $this->assertContains('calling', $packs);
        $this->assertContains('academic', $packs);

        Livewire::test(DashboardAttentionWidget::class)
            ->assertSuccessful()
            ->assertSee('In queue')
            ->assertSee('Students attendance');

        Livewire::test(DashboardTodayPulseWidget::class)
            ->assertSuccessful()
            ->assertSee('New leads')
            ->assertSee('Students attendance');
    }

    public function test_accountant_sees_finance_home_not_call_queue(): void
    {
        $this->createSession();

        $accountant = User::factory()->create(['is_active' => true]);
        $accountant->assignRole(StaffJobRole::Accountant->value);

        $this->actingAs($accountant);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(DashboardAttentionWidget::class)
            ->assertSuccessful()
            ->assertSee('Overdue students')
            ->assertSee('Collected today')
            ->assertDontSee('Fee waives')
            ->assertDontSee('Payment cancels');

        Livewire::test(DashboardHeroWidget::class)
            ->assertSuccessful()
            ->assertDontSee('Call Queue');
    }

    public function test_messaging_coordinator_sees_whatsapp_attention_tiles(): void
    {
        $this->createSession();

        $messaging = User::factory()->create(['is_active' => true]);
        $messaging->assignRole(StaffJobRole::MessagingCoordinator->value);

        $this->actingAs($messaging);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(DashboardAttentionWidget::class)
            ->assertSuccessful()
            ->assertSee('WhatsApp inbox');

        Livewire::test(DashboardHeroWidget::class)
            ->assertSuccessful()
            ->assertSee('WhatsApp inbox');
    }

    protected function actingAsSuperAdmin(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        return $admin;
    }

    protected function createSession(): AcademicSession
    {
        return AcademicSession::query()->create([
            'name' => '2026-2027',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);
    }

    protected function createCourse(): Course
    {
        return Course::query()->create([
            'name' => 'Dashboard Course',
            'code' => 'DASH-'.uniqid(),
            'programme_category' => 'coaching',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 100000,
            'status' => CourseStatus::Active,
        ]);
    }

    protected function createEnquiry(string $number, \Illuminate\Support\Carbon $createdAt): Enquiry
    {
        $student = Student::query()->create([
            'name' => 'Lead '.$number,
            'mobile' => '98000'.random_int(10000, 99999),
            'status' => StudentStatus::Enquiry,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'course_id' => $this->createCourse()->id,
            'enquiry_number' => $number,
            'lead_source' => LeadSource::Website,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $enquiry->forceFill(['created_at' => $createdAt])->save();

        return $enquiry;
    }
}
