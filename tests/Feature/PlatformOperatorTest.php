<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PlatformOperatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_operator_is_hidden_from_staff_list(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $schoolAdmin = User::factory()->create(['mobile' => '9111111111']);
        $schoolAdmin->assignRole(RoleName::SuperAdmin->value);

        User::factory()->create([
            'mobile' => '9222222222',
            'is_platform_operator' => true,
        ]);

        $ids = StaffResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($schoolAdmin->id, $ids);
        $this->assertCount(1, $ids);
    }

    public function test_platform_operator_can_only_access_platform_panel(): void
    {
        $operator = User::factory()->create([
            'is_platform_operator' => true,
            'is_active' => true,
        ]);

        $adminPanel = Filament::getPanel('admin');
        $platformPanel = Filament::getPanel('platform');

        $this->assertTrue($operator->canAccessPanel($platformPanel));
        $this->assertFalse($operator->canAccessPanel($adminPanel));
    }

    public function test_school_staff_cannot_access_platform_panel(): void
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $schoolAdmin = User::factory()->create(['is_platform_operator' => false]);
        $schoolAdmin->assignRole(RoleName::SuperAdmin->value);

        $platformPanel = Filament::getPanel('platform');

        $this->assertFalse($schoolAdmin->canAccessPanel($platformPanel));
    }
}
