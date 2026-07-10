<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Pages\BackupsPage;
use App\Models\User;
use App\Services\CrmBackupService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class CrmBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'crm-backup.disk_path' => storage_path('app/private/backups-test'),
            'crm-backup.retain' => 3,
        ]);

        File::deleteDirectory(storage_path('app/private/backups-test'));
        File::ensureDirectoryExists(storage_path('app/private/backups-test'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/private/backups-test'));

        parent::tearDown();
    }

    public function test_full_backup_zip_contains_database_files_and_app_key(): void
    {
        Storage::disk('local')->put('documents/99/photo/sample.jpg', 'photo-bytes');
        Storage::disk('public')->put('homework/notes.pdf', 'homework-bytes');
        Storage::disk('public')->put('site/logo/logo.png', 'logo-bytes');

        // Must not be included
        Storage::disk('local')->put('backups/should-not-copy.zip', 'x');
        Storage::disk('local')->put('livewire-tmp/tmp.bin', 'tmp');

        $result = app(CrmBackupService::class)->create();

        $this->assertFileExists($result['path']);
        $this->assertGreaterThan(0, $result['size_bytes']);
        $this->assertGreaterThan(0, $result['tables']);
        $this->assertGreaterThanOrEqual(1, $result['private_files']);
        $this->assertGreaterThanOrEqual(2, $result['public_files']);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($result['path']) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = $zip->getNameIndex($i);
        }

        $this->assertContains('manifest.json', $names);
        $this->assertContains('database.sql', $names);
        $this->assertContains('app-key.txt', $names);
        $this->assertContains('env-snapshot.json', $names);
        $this->assertContains('RESTORE.txt', $names);
        $this->assertTrue(collect($names)->contains(fn (string $n): bool => str_contains($n, 'documents/99/photo/sample.jpg')));
        $this->assertTrue(collect($names)->contains(fn (string $n): bool => str_contains($n, 'homework/notes.pdf')));
        $this->assertTrue(collect($names)->contains(fn (string $n): bool => str_contains($n, 'site/logo/logo.png')));
        $this->assertFalse(collect($names)->contains(fn (string $n): bool => str_contains($n, 'livewire-tmp')));
        $this->assertFalse(collect($names)->contains(fn (string $n): bool => str_contains($n, 'should-not-copy.zip')));

        $manifest = json_decode((string) $zip->getFromName('manifest.json'), true);
        $this->assertSame('school-crm-full-backup-v1', $manifest['format']);
        $this->assertSame(config('app.key'), trim((string) $zip->getFromName('app-key.txt')));

        $sql = (string) $zip->getFromName('database.sql');
        $this->assertStringContainsString('CREATE TABLE', $sql);
        $this->assertStringContainsString('users', $sql);

        $zip->close();
    }

    public function test_backup_retention_prunes_old_archives(): void
    {
        $service = app(CrmBackupService::class);

        $service->create();
        $service->create();
        $service->create();
        $service->create();

        $this->assertCount(3, $service->listBackups());
    }

    public function test_super_admin_can_access_backups_page(): void
    {
        Role::findOrCreate(RoleName::SuperAdmin->value);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->assertTrue(BackupsPage::canAccess());

        Livewire::test(BackupsPage::class)
            ->assertOk()
            ->assertSee('Full institute backup');
    }

    public function test_staff_cannot_download_backup(): void
    {
        Role::findOrCreate(RoleName::Staff->value);
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole(RoleName::Staff->value);

        $result = app(CrmBackupService::class)->create();

        $this->actingAs($staff)
            ->get(route('admin.backups.download', ['filename' => $result['filename']]))
            ->assertForbidden();
    }
}
