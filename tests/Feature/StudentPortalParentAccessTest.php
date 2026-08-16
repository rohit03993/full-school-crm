<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\LicenseFeature;
use App\Enums\StudentStatus;
use App\Models\Setting;
use App\Models\Student;
use App\Services\StudentAuthService;
use App\Support\FeatureGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentPortalParentAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::setValue(
            'portal.shared_password_hash',
            app(StudentAuthService::class)->hashPortalPassword('Portal@2026'),
            'portal',
        );
    }

    public function test_parent_mobile_on_alternate_can_login_and_switch_children(): void
    {
        $childA = Student::query()->create([
            'name' => 'Child Alpha',
            'father_name' => 'Parent',
            'date_of_birth' => '2012-01-01',
            'gender' => Gender::Male,
            'mobile' => '9811000101',
            'alternate_mobile' => '9811000999',
            'status' => StudentStatus::Enrolled,
            'portal_password' => null,
        ]);

        $childB = Student::query()->create([
            'name' => 'Child Beta',
            'father_name' => 'Parent',
            'date_of_birth' => '2014-02-02',
            'gender' => Gender::Female,
            'mobile' => '9811000102',
            'alternate_mobile' => '9811000999',
            'status' => StudentStatus::Enrolled,
            'portal_password' => null,
        ]);

        $this->post(route('portal.login.submit'), [
            'mobile' => '9811000999',
            'password' => 'Portal@2026',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertSame('9811000999', session('portal_login_mobile'));
        $this->assertContains(session('student_portal_id'), [$childA->id, $childB->id]);

        $activeId = (int) session('student_portal_id');
        $otherId = $activeId === $childA->id ? $childB->id : $childA->id;

        $this->post(route('portal.switch-child'), [
            'student_id' => $otherId,
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertSame($otherId, (int) session('student_portal_id'));

        $this->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Parent &amp; Student', false)
            ->assertSee('Switch child')
            ->assertSee('Child Alpha')
            ->assertSee('Child Beta');
    }

    public function test_cannot_switch_to_unrelated_student(): void
    {
        $linked = Student::query()->create([
            'name' => 'Linked Child',
            'father_name' => 'Parent',
            'date_of_birth' => '2012-01-01',
            'gender' => Gender::Male,
            'mobile' => '9811000201',
            'alternate_mobile' => '9811000888',
            'status' => StudentStatus::Enrolled,
            'portal_password' => null,
        ]);

        $stranger = Student::query()->create([
            'name' => 'Stranger Child',
            'father_name' => 'Other',
            'date_of_birth' => '2013-03-03',
            'gender' => Gender::Male,
            'mobile' => '9811000202',
            'status' => StudentStatus::Enrolled,
            'portal_password' => null,
        ]);

        $this->post(route('portal.login.submit'), [
            'mobile' => '9811000888',
            'password' => 'Portal@2026',
        ])->assertRedirect(route('portal.dashboard'));

        $this->assertSame($linked->id, (int) session('student_portal_id'));

        $this->from(route('portal.dashboard'))
            ->post(route('portal.switch-child'), [
                'student_id' => $stranger->id,
            ])
            ->assertRedirect(route('portal.dashboard'))
            ->assertSessionHasErrors('student_id');

        $this->assertSame($linked->id, (int) session('student_portal_id'));
    }

    public function test_portal_shows_attendance_tab_when_module_enabled(): void
    {
        $this->assertTrue(FeatureGate::enabled(LicenseFeature::Attendance));

        Student::query()->create([
            'name' => 'Attendance Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2011-06-06',
            'gender' => Gender::Female,
            'mobile' => '9811000303',
            'status' => StudentStatus::Enrolled,
            'portal_password' => null,
        ]);

        $this->post(route('portal.login.submit'), [
            'mobile' => '9811000303',
            'password' => 'Portal@2026',
        ])->assertRedirect(route('portal.dashboard'));

        $this->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee('Attendance')
            ->assertSee('id="attendance_month"', false);
    }

    public function test_login_page_uses_parent_student_wording(): void
    {
        $this->get(route('portal.login'))
            ->assertOk()
            ->assertSee('Parent &amp; Student', false);
    }
}
