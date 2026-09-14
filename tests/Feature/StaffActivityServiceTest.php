<?php

namespace Tests\Feature;

use App\Enums\CallDirection;
use App\Enums\CallStatus;
use App\Enums\CrmPermission;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\StaffActivityRange;
use App\Enums\StaffActivityType;
use App\Enums\StaffJobRole;
use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\StudentCall;
use App\Models\User;
use App\Services\CrmPermissionSyncService;
use App\Services\StaffActivityService;
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
        $this->assertTrue($service->canView($counsellor, $counsellor));
    }
}
