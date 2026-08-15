<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\LicenseFeature;
use App\Enums\LicensePlan;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Enums\VisitStatus;
use App\Filament\Pages\CampusVisitsPage;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\Visit;
use App\Services\LicenseService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CampusVisitsMobileUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mid-week, mid-month so today / week / month presets cannot overlap
        Carbon::setTestNow('2026-08-12 10:00:00');

        Role::findOrCreate(RoleName::SuperAdmin->value);

        Setting::query()->whereIn('key', [
            LicenseService::PAYLOAD_KEY,
            LicenseService::SIGNATURE_KEY,
        ])->delete();
        Setting::flushValueCache();

        app(LicenseService::class)->save([
            'plan' => LicensePlan::Custom->value,
            'features' => array_map(
                fn (LicenseFeature $feature): string => $feature->value,
                LicenseFeature::cases(),
            ),
            'expires_at' => now()->addYear()->toDateString(),
        ]);

        Setting::setValue('site.name', 'Test Institute', 'general');
        Setting::setValue('crm.onboarding_completed', '1', 'crm');

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);
        $this->actingAs($admin);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_visit_renders_as_a_card_with_badge_avatar_and_call_action(): void
    {
        $staff = User::factory()->create(['is_active' => true, 'name' => 'Khushi Mam']);

        $student = Student::query()->create([
            'name' => 'Aarav Bundal',
            'father_name' => 'Parent',
            'date_of_birth' => '2009-05-01',
            'gender' => Gender::Male,
            'mobile' => '9876500011',
            'status' => StudentStatus::Enquiry,
        ]);

        Visit::query()->create([
            'student_id' => $student->id,
            'visit_date' => now()->toDateString(),
            'staff_user_id' => $staff->id,
            'discussion_summary' => 'Walked in for a demo class.',
            'status' => VisitStatus::Joined,
        ]);

        $this->get(CampusVisitsPage::getUrl())
            ->assertOk()
            ->assertSee('crm-stat-grid', false)
            ->assertSee('crm-responsive-table__title', false)
            ->assertSee('crm-responsive-table__actions', false)
            ->assertSee('crm-avatar', false)
            ->assertSee('crm-badge--success', false)
            ->assertSee('tel:9876500011', false)
            ->assertSee('Aarav Bundal')
            ->assertSee('Khushi Mam')
            ->assertSee('Joined');
    }

    public function test_period_control_marks_the_active_range(): void
    {
        $page = Livewire::test(CampusVisitsPage::class);

        $this->assertSame('2026-08-01', $page->get('dateFrom'));
        $this->assertSame('month', $page->instance()->periodPreset());

        $page->call('setPeriodToday');
        $this->assertSame('today', $page->instance()->periodPreset());
        $page->assertSee('crm-seg__btn--active', false);

        $page->call('setPeriodThisWeek');
        $this->assertSame('week', $page->instance()->periodPreset());

        $page->set('dateFrom', '2026-07-04');
        $this->assertSame('custom', $page->instance()->periodPreset());
    }

    public function test_every_visit_status_has_a_badge_tone(): void
    {
        $this->assertSame('success', VisitStatus::Joined->tone());
        $this->assertSame('warning', VisitStatus::FollowUpRequired->tone());
        $this->assertSame('danger', VisitStatus::NotInterested->tone());

        foreach (VisitStatus::cases() as $status) {
            $this->assertContains(
                $status->tone(),
                ['gray', 'success', 'info', 'warning', 'danger'],
            );
        }
    }
}
