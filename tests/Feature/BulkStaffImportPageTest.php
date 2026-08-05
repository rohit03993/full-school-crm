<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Pages\BulkStaffImportPage;
use App\Models\StaffProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkStaffImportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_accepts_stored_path_string_from_file_upload(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        $relative = 'temp-staff-imports/staff-import.csv';
        Storage::disk('local')->put($relative, implode("\n", [
            'Staff ID,Name,Mobile,Designation,Email',
            'STF101,Anita Sharma,9876543210,Teacher,',
            'STF102,Ravi Kumar,9123456780,Counsellor,',
        ])."\n");

        Livewire::test(BulkStaffImportPage::class)
            ->set('data.upload', $relative)
            ->call('importStaff')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('staff_profiles', [
            'employee_code' => 'STF101',
        ]);
        $this->assertDatabaseHas('staff_profiles', [
            'employee_code' => 'STF102',
        ]);
        $this->assertSame(2, StaffProfile::query()->count());
    }

    public function test_import_accepts_upload_wrapped_in_array(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin();
        $this->actingAs($admin);

        $relative = 'temp-staff-imports/staff-import-array.csv';
        Storage::disk('local')->put($relative, implode("\n", [
            'Staff ID,Name,Mobile',
            'STF201,Neha Verma,9988776655',
        ])."\n");

        Livewire::test(BulkStaffImportPage::class)
            ->set('data.upload', [$relative])
            ->call('importStaff')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('staff_profiles', [
            'employee_code' => 'STF201',
        ]);
    }

    protected function makeAdmin(): User
    {
        Role::findOrCreate(RoleName::SuperAdmin->value);
        Role::findOrCreate(RoleName::Staff->value);

        $user = User::factory()->create([
            'email' => 'staff-import-admin@example.com',
        ]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }
}
