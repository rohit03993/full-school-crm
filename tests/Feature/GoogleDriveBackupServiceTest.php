<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\Setting;
use App\Models\User;
use App\Services\CrmBackupService;
use App\Services\GoogleDriveBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;
use ZipArchive;

class GoogleDriveBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'crm-backup.disk_path' => storage_path('app/private/backups-test'),
            'crm-backup.retain' => 2,
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
        ]);

        File::deleteDirectory(storage_path('app/private/backups-test'));
        File::ensureDirectoryExists(storage_path('app/private/backups-test'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/private/backups-test'));
        Setting::flushValueCache();

        parent::tearDown();
    }

    public function test_inspect_backup_detects_app_key_mismatch(): void
    {
        $result = app(CrmBackupService::class)->create();
        $path = $result['path'];

        $tmp = storage_path('app/private/backups-test/tampered.zip');
        copy($path, $tmp);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $zip->addFromString('app-key.txt', "base64:wrong-key\n");
        $zip->close();

        $inspection = app(CrmBackupService::class)->inspectBackupZip($tmp);

        $this->assertTrue($inspection['valid']);
        $this->assertFalse($inspection['app_key_matches']);
        $this->assertStringContainsString('APP_KEY', $inspection['message']);
    }

    public function test_google_drive_upload_uses_api_when_configured(): void
    {
        config(['crm-backup.gdrive_test_access_token' => 'ya29.test-token']);

        $serviceJson = json_encode([
            'type' => 'service_account',
            'project_id' => 'test-project',
            'private_key_id' => 'abc',
            'private_key' => "-----BEGIN PRIVATE KEY-----\nTEST\n-----END PRIVATE KEY-----\n",
            'client_email' => 'backup@test-project.iam.gserviceaccount.com',
            'client_id' => '123',
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ], JSON_THROW_ON_ERROR);

        Setting::setValue('backup.gdrive.enabled', '1', 'backup');
        Setting::setValue('backup.gdrive.folder_id', 'folder-123', 'backup');
        Setting::setValue('backup.gdrive.service_account_json', Crypt::encryptString($serviceJson), 'backup');
        Setting::flushValueCache();

        Http::fake([
            'https://www.googleapis.com/upload/drive/v3/files*' => Http::response([
                'id' => 'drive-file-1',
                'name' => 'school-crm-full-backup-test.zip',
                'webViewLink' => 'https://drive.google.com/file/d/drive-file-1/view',
            ], 200),
            'https://www.googleapis.com/drive/v3/files*' => Http::response([
                'files' => [],
            ], 200),
        ]);

        $backup = app(CrmBackupService::class)->create();
        $upload = app(GoogleDriveBackupService::class)->uploadBackup($backup['path'], $backup['filename']);

        $this->assertSame('drive-file-1', $upload['file_id']);
        $this->assertSame($backup['filename'], Setting::getValue('backup.gdrive.last_upload_filename'));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'upload/drive/v3/files'));
    }

    public function test_drive_not_ready_without_credentials(): void
    {
        $this->assertFalse(app(GoogleDriveBackupService::class)->isReady());
    }

    public function test_staff_cannot_open_backups_page(): void
    {
        Role::findOrCreate(RoleName::Staff->value);
        $staff = User::factory()->create(['is_active' => true]);
        $staff->assignRole(RoleName::Staff->value);

        $this->actingAs($staff);

        $this->assertFalse(\App\Filament\Pages\BackupsPage::canAccess());
    }
}
