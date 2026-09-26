<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\CallDirection;
use App\Enums\CallStatus;
use App\Enums\CrmPermission;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\StaffActivityRange;
use App\Enums\StaffActivityType;
use App\Enums\StaffJobRole;
use App\Enums\StudentStatus;
use App\Models\StaffLoginSession;
use App\Models\Student;
use App\Models\StudentCall;
use App\Models\User;
use App\Services\CrmPermissionSyncService;
use Spatie\Permission\Models\Role;
use App\Services\StaffActivityService;
use App\Services\StaffActivityTimelineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffActivityServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(CrmPermissionSyncService::class)->sync();
    }

    public function test_counsellor_sees_own_calls_and_not_fee_tiles(): void
    {
        $counsellor = User::factory()->create(['is_active' => true]);
        $counsellor->assignRole(StaffJobRole::Counsellor->value);

        $student = Student::query()->create([
            'name' => 'Call Student',
            'mobile' => '9000001100',
            'gender' => Gender::Male,
            'status' => StudentStatus::Enquiry,
            'lead_source' => LeadSource::WalkIn,
        ]);

        StudentCall::query()->create([
            'student_id' => $student->id,
            'user_id' => $counsellor->id,
            'called_at' => now(),
            'call_direction' => CallDirection::Outgoing,
            'call_status' => CallStatus::Connected,
        ]);

        $service = app(StaffActivityService::class);
        $tiles = collect($service->tiles($counsellor, StaffActivityRange::Today))->keyBy('key');

        $this->assertTrue($tiles->has(StaffActivityType::Calls->value));
        $this->assertSame('1', $tiles[StaffActivityType::Calls->value]['value']);
        $this->assertTrue($tiles->has(StaffActivityType::Attendance->value));
        $this->assertFalse($tiles->has(StaffActivityType::FeesCollected->value));
        $this->assertFalse($tiles->has(StaffActivityType::Homework->value));
    }

    public function test_teacher_does_not_see_call_tiles(): void
    {
        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->assignRole(StaffJobRole::Teacher->value);

        $tiles = collect(app(StaffActivityService::class)->tiles($teacher, StaffActivityRange::Today))->keyBy('key');

        $this->assertFalse($tiles->has(StaffActivityType::Calls->value));
        $this->assertTrue($tiles->has(StaffActivityType::AttendanceMarked->value));
        $this->assertFalse($teacher->canCrm(CrmPermission::LeadsCall));
    }

    public function test_staff_cannot_view_another_persons_activity(): void
    {
        $counsellor = User::factory()->create(['is_active' => true]);
        $counsellor->assignRole(StaffJobRole::Counsellor->value);
        $other = User::factory()->create(['is_active' => true]);
        $other->assignRole(StaffJobRole::Teacher->value);

        $service = app(StaffActivityService::class);

        $this->assertFalse($service->canView($counsellor, $other));
        $this->assertFalse($service->canView($counsellor, $counsellor));

        Role::findOrCreate(RoleName::SuperAdmin->value);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $this->assertTrue($service->canView($admin, $other));
        $this->assertTrue($service->canView($admin, $counsellor));
    }

    public function test_timeline_orders_login_and_call_without_other_roles(): void
    {
        $counsellor = User::factory()->create(['is_active' => true]);
        $counsellor->assignRole(StaffJobRole::Counsellor->value);

        $student = Student::query()->create([
            'name' => 'Timeline Student',
            'mobile' => '9000001101',
            'gender' => Gender::Male,
            'status' => StudentStatus::Enquiry,
            'lead_source' => LeadSource::WalkIn,
        ]);

        StaffLoginSession::query()->create([
            'user_id' => $counsellor->id,
            'logged_in_at' => now()->subHour(),
            'logged_out_at' => now()->subMinutes(10),
            'method' => 'password',
        ]);

        StudentCall::query()->create([
            'student_id' => $student->id,
            'user_id' => $counsellor->id,
            'called_at' => now()->subMinutes(30),
            'call_direction' => CallDirection::Outgoing,
            'call_status' => CallStatus::Connected,
        ]);

        $items = collect(app(StaffActivityTimelineService::class)
            ->forStaff($counsellor, StaffActivityRange::Today)['items']);

        $this->assertSame(
            ['Logged out', 'Logged a call', 'Logged in to the CRM'],
            $items->pluck('title')->all(),
        );
        $this->assertFalse($items->contains(fn (array $item): bool => $item['category'] === 'Fees'));
    }
}
