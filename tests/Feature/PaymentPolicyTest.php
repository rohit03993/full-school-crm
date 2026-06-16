<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Payment;
use App\Models\User;
use App\Policies\PaymentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_create_but_not_update_payments(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole(RoleName::Staff->value);

        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $payment = new Payment;
        $policy = new PaymentPolicy;

        $this->assertTrue($policy->create($staff));
        $this->assertFalse($policy->update($staff, $payment));
        $this->assertFalse($policy->delete($staff, $payment));

        $this->assertTrue($policy->update($admin, $payment));
        $this->assertTrue($policy->delete($admin, $payment));
    }
}
