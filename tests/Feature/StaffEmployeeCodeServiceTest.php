<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use App\Services\StaffEmployeeCodeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffEmployeeCodeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleName::Staff->value);
    }

    public function test_assigns_1001_when_no_numeric_codes_exist(): void
    {
        $user = $this->makeStaffWithoutCode('Aneeta');

        $code = app(StaffEmployeeCodeService::class)->ensureForUser($user);

        $this->assertSame('1001', $code);
        $this->assertSame('1001', $user->fresh()->staffProfile->employee_code);
    }

    public function test_keeps_existing_non_numeric_staff_id(): void
    {
        $user = $this->makeStaffWithCode('Aditya', 'STF012');

        $code = app(StaffEmployeeCodeService::class)->ensureForUser($user);

        $this->assertSame('STF012', $code);
        $this->assertSame('STF012', $user->fresh()->staffProfile->employee_code);
    }

    public function test_backfill_skips_existing_and_fills_blank(): void
    {
        $withCode = $this->makeStaffWithCode('Ajay', 'STF002');
        $blankA = $this->makeStaffWithoutCode('Khushi');
        $blankB = $this->makeStaffWithoutCode('Rohit');

        $result = app(StaffEmployeeCodeService::class)->backfillMissing();

        $this->assertSame(2, $result['assigned']);
        $this->assertGreaterThanOrEqual(1, $result['skipped']);
        $this->assertSame('STF002', $withCode->fresh()->staffProfile->employee_code);
        $this->assertSame('1001', $blankA->fresh()->staffProfile->employee_code);
        $this->assertSame('1002', $blankB->fresh()->staffProfile->employee_code);
    }

    public function test_next_code_continues_after_highest_numeric(): void
    {
        $this->makeStaffWithCode('One', '1005');
        $this->makeStaffWithCode('Legacy', 'STF099');

        $blank = $this->makeStaffWithoutCode('New');

        $code = app(StaffEmployeeCodeService::class)->ensureForUser($blank);

        $this->assertSame('1006', $code);
    }

    protected function makeStaffWithoutCode(string $name): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'is_active' => true,
        ]);
        $user->assignRole(RoleName::Staff->value);
        $user->staffProfile()->create([
            'employee_code' => null,
        ]);

        return $user->fresh(['staffProfile']);
    }

    protected function makeStaffWithCode(string $name, string $code): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'is_active' => true,
        ]);
        $user->assignRole(RoleName::Staff->value);
        $user->staffProfile()->create([
            'employee_code' => $code,
        ]);

        return $user->fresh(['staffProfile']);
    }
}
