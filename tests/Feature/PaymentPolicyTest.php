<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Models\Payment;
use App\Models\User;
use App\Policies\PaymentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_collecting_fees_needs_a_money_job_role(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $genericStaff = User::factory()->create(['is_active' => true]);
        $genericStaff->assignRole(RoleName::Staff->value);

        $accountant = User::factory()->create(['is_active' => true]);
        $accountant->assignRole(StaffJobRole::Accountant->value);

        $policy = new PaymentPolicy;
        $payment = new Payment;

        // A login with no money role must not touch cash.
        $this->assertFalse($policy->create($genericStaff));
        $this->assertFalse($policy->update($genericStaff, $payment));
        $this->assertFalse($policy->delete($genericStaff, $payment));

        $this->assertTrue($policy->create($accountant));
    }

    public function test_only_fee_adjuster_can_change_a_recorded_payment(): void
    {
        $accountant = User::factory()->create(['is_active' => true]);
        $accountant->assignRole(StaffJobRole::Accountant->value);

        $adjuster = User::factory()->create(['is_active' => true]);
        $adjuster->assignRole(StaffJobRole::FeeAdjuster->value);

        $policy = new PaymentPolicy;
        $payment = new Payment;

        $this->assertFalse($policy->update($accountant, $payment));
        $this->assertFalse($policy->delete($accountant, $payment));

        $this->assertTrue($policy->update($adjuster, $payment));
        $this->assertTrue($policy->delete($adjuster, $payment));
    }

    public function test_super_admin_keeps_full_payment_control(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $policy = new PaymentPolicy;
        $payment = new Payment;

        $this->assertTrue($policy->create($admin));
        $this->assertTrue($policy->update($admin, $payment));
        $this->assertTrue($policy->delete($admin, $payment));
    }
}
