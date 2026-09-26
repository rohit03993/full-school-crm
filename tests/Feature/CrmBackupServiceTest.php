<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Filament\Pages\BackupsPage;
use App\Models\Setting;
use App\Models\User;
use App\Services\CrmBackupService;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class CrmBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    protected string $backupTestRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backupTestRoot = storage_path('framework/testing/crm-backup');

        config([
            'crm-backup.disk_path' => $this->backupTestRoot.'/zips',
            'crm-backup.private_storage_path' => $this->backupTestRoot.'/private',
            'crm-backup.public_storage_path' => $this->backupTestRoot.'/public',
            'crm-backup.retain' => 3,
        ]);

        File::deleteDirectory($this->backupTestRoot);
        File::ensureDirectoryExists($this->backupTestRoot.'/private');
        File::ensureDirectoryExists($this->backupTestRoot.'/public');
        File::ensureDirectoryExists($this->backupTestRoot.'/zips');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->backupTestRoot);

        parent::tearDown();
    }

    protected function putBackupFile(string $kind, string $relative, string $bytes): void
    {
        $root = $kind === 'public'
            ? config('crm-backup.public_storage_path')
            : config('crm-backup.private_storage_path');
        $path = $root.'/'.$relative;
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $bytes);
    }

    public function test_full_backup_zip_contains_database_files_and_app_key(): void
    {
        $this->putBackupFile('private', 'documents/99/photo/sample.jpg', 'photo-bytes');
        $this->putBackupFile('public', 'homework/notes.pdf', 'homework-bytes');
        $this->putBackupFile('public', 'site/logo/logo.png', 'logo-bytes');

        // Must not be included
        $this->putBackupFile('private', 'backups/should-not-copy.zip', 'x');
        $this->putBackupFile('private', 'livewire-tmp/tmp.bin', 'tmp');

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
            ->assertSee('Full institute backup')
            ->assertSee('Upload and restore');
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

    public function test_backup_includes_every_current_table_and_later_files(): void
    {
        $probeEmail = 'backup-probe-staff@school.test';
        $probeSetup = 'BACKUP-PROBE-SCHOOL-SETUP';

        $user = User::factory()->create([
            'name' => 'Backup Probe Staff',
            'email' => $probeEmail,
        ]);

        DB::table('staff_login_sessions')->insert([
            'user_id' => $user->id,
            'logged_in_at' => now(),
            'method' => 'password',
            'ip_address' => '10.1.1.1',
            'user_agent' => 'probe',
            'session_key' => 'probe-session',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Setting::setValue('crm.backup_probe', $probeSetup, 'crm');

        $privateFiles = [
            'whatsapp-media/probe.jpg' => 'wa',
            'certificates/probe.pdf' => 'cert',
            'payments/1/probe.jpg' => 'pay',
            'receipts/probe.pdf' => 'rcpt',
            'id_cards/probe.pdf' => 'id',
            'marksheets/probe.pdf' => 'ms',
        ];

        $publicFiles = [
            'crm/branding/probe.png' => 'brand',
            'homework/probe.pdf' => 'hw',
            'site/gallery/probe.png' => 'gallery',
        ];

        foreach ($privateFiles as $path => $bytes) {
            $this->putBackupFile('private', $path, $bytes);
        }

        foreach ($publicFiles as $path => $bytes) {
            $this->putBackupFile('public', $path, $bytes);
        }

        try {
            $result = app(CrmBackupService::class)->create();

            $zip = new ZipArchive;
            $this->assertTrue($zip->open($result['path']) === true);

            $sql = (string) $zip->getFromName('database.sql');
            $names = [];

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $names[] = $zip->getNameIndex($i);
            }

            $zip->close();

            $tables = collect(DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'"))
                ->pluck('name');

            $this->assertNotEmpty($tables);

            foreach ($tables as $table) {
                $this->assertStringContainsString(
                    '-- Table: '.$table,
                    $sql,
                    'Missing table from backup: '.$table,
                );
            }

            $this->assertStringContainsString('leave_reason', $sql);
            $this->assertStringContainsString($probeEmail, $sql);
            $this->assertStringContainsString($probeSetup, $sql);
            $this->assertStringContainsString('parent_fee_notices', $sql);
            $this->assertStringContainsString('homework_student_links', $sql);
            $this->assertStringContainsString('student_cases', $sql);
            $this->assertStringContainsString('student_calls', $sql);
            $this->assertStringContainsString('enquiries', $sql);
            $this->assertStringContainsString('biometric_punches', $sql);

            foreach (array_merge(array_keys($privateFiles), array_keys($publicFiles)) as $file) {
                $this->assertTrue(
                    collect($names)->contains(fn (string $name): bool => str_contains($name, $file)),
                    'Missing file from backup: '.$file,
                );
            }
        } finally {
            foreach (array_keys($privateFiles) as $path) {
                File::delete(config('crm-backup.private_storage_path').'/'.$path);
            }

            foreach (array_keys($publicFiles) as $path) {
                File::delete(config('crm-backup.public_storage_path').'/'.$path);
            }
        }
    }

    public function test_restore_puts_database_row_and_files_back(): void
    {
        $this->putBackupFile('private', 'documents/1/photo/a.jpg', 'student-photo');
        $this->putBackupFile('public', 'homework/hw.pdf', 'homework-file');
        Setting::setValue('crm.backup_probe', 'RESTORE-ME', 'crm');

        $result = app(CrmBackupService::class)->create();

        Setting::setValue('crm.backup_probe', 'WIPED', 'crm');
        File::delete(config('crm-backup.private_storage_path').'/documents/1/photo/a.jpg');
        File::delete(config('crm-backup.public_storage_path').'/homework/hw.pdf');

        app(CrmBackupService::class)->restore($result['path'], force: true);

        $this->assertSame('RESTORE-ME', Setting::getValue('crm.backup_probe'));
        $this->assertSame(
            'student-photo',
            File::get(config('crm-backup.private_storage_path').'/documents/1/photo/a.jpg'),
        );
        $this->assertSame(
            'homework-file',
            File::get(config('crm-backup.public_storage_path').'/homework/hw.pdf'),
        );
    }

    public function test_super_admin_can_upload_backup_in_pieces(): void
    {
        Role::findOrCreate(RoleName::SuperAdmin->value);
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $uploadId = '0123456789abcdef0123456789abcdef';
        $directory = storage_path('app/private/.restore-upload');
        $stored = $directory.'/school-crm-full-backup-upload-'.$uploadId.'.zip';

        try {
            $this->actingAs($admin)
                ->post(route('admin.backups.restore-chunk'), [
                    'upload_id' => $uploadId,
                    'index' => 0,
                    'total' => 2,
                    'original_name' => 'school-crm-full-backup-2026-09-26_120000.zip',
                    'chunk' => UploadedFile::fake()->createWithContent('chunk.bin', 'AAA'),
                ])
                ->assertOk()
                ->assertJson(['done' => false]);

            $this->actingAs($admin)
                ->post(route('admin.backups.restore-chunk'), [
                    'upload_id' => $uploadId,
                    'index' => 1,
                    'total' => 2,
                    'original_name' => 'school-crm-full-backup-2026-09-26_120000.zip',
                    'chunk' => UploadedFile::fake()->createWithContent('chunk.bin', 'BBB'),
                ])
                ->assertOk()
                ->assertJsonPath('done', true)
                ->assertJsonPath('stored_name', 'school-crm-full-backup-upload-'.$uploadId.'.zip');

            $this->assertSame('AAABBB', File::get($stored));
        } finally {
            @unlink($stored);
            @unlink($directory.'/'.$uploadId.'.part');
            @unlink($directory.'/'.$uploadId.'.json');
        }
    }

    public function test_staff_cannot_upload_backup_pieces(): void
    {
        Role::findOrCreate(RoleName::Staff->value);
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole(RoleName::Staff->value);

        $this->actingAs($staff)
            ->post(route('admin.backups.restore-chunk'), [
                'upload_id' => '0123456789abcdef0123456789abcdef',
                'index' => 0,
                'total' => 1,
                'original_name' => 'school-crm-full-backup-2026-09-26_120000.zip',
                'chunk' => UploadedFile::fake()->createWithContent('chunk.bin', 'AAA'),
            ])
            ->assertForbidden();
    }
}
