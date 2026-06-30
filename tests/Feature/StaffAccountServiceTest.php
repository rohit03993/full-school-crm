<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\StaffAccountService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class StaffAccountServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_own_mobile_when_unique(): void
    {
        $user = User::factory()->create([
            'mobile' => '9876543210',
            'password' => Hash::make('OldPassword1'),
            'is_active' => true,
        ]);

        $other = User::factory()->create([
            'mobile' => '9811000001',
            'password' => Hash::make('OtherPass1'),
            'is_active' => true,
        ]);

        $updated = app(StaffAccountService::class)->updateOwnAccount($user, [
            'mobile' => '9027620525',
            'current_password' => 'OldPassword1',
        ]);

        $this->assertSame('9027620525', $updated->mobile);
        $this->assertTrue(Hash::check('OldPassword1', $updated->password));
        $this->assertSame('9811000001', $other->fresh()->mobile);
    }

    public function test_user_can_update_password(): void
    {
        $user = User::factory()->create([
            'mobile' => '9876543210',
            'password' => Hash::make('OldPassword1'),
            'is_active' => true,
        ]);

        app(StaffAccountService::class)->updateOwnAccount($user, [
            'mobile' => '9876543210',
            'current_password' => 'OldPassword1',
            'new_password' => 'NewPassword2',
            'new_password_confirmation' => 'NewPassword2',
        ]);

        $user->refresh();
        $this->assertTrue(Hash::check('NewPassword2', $user->password));
    }

    public function test_mobile_already_used_by_another_account_is_rejected(): void
    {
        $user = User::factory()->create([
            'mobile' => '9876543210',
            'password' => Hash::make('OldPassword1'),
            'is_active' => true,
        ]);

        User::factory()->create([
            'mobile' => '9811000002',
            'password' => Hash::make('OtherPass1'),
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(StaffAccountService::class)->updateOwnAccount($user, [
            'mobile' => '9811000002',
            'current_password' => 'OldPassword1',
        ]);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create([
            'mobile' => '9876543210',
            'password' => Hash::make('OldPassword1'),
            'is_active' => true,
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(StaffAccountService::class)->updateOwnAccount($user, [
            'mobile' => '9027620525',
            'current_password' => 'WrongPassword',
        ]);
    }
}
