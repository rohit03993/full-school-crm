<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Models\Setting;
use App\Services\AuditService;
use App\Services\CrmBackupService;
use App\Services\GoogleDriveBackupService;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;
use UnitEnum;

class BackupsPage extends Page
{
    use WithFileUploads;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 55;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_SETTINGS;

    public bool $gdriveEnabled = false;

    public string $gdriveFolderId = '';

    public string $gdriveClientId = '';

    public string $gdriveClientSecret = '';

    public bool $restoreConfirmed = false;

    public ?TemporaryUploadedFile $restoreUpload = null;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::backups();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::backups();
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('setup.backups');
    }

    public static function canAccess(): bool
    {
        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public function mount(GoogleDriveBackupService $drive): void
    {
        $status = $drive->status();
        $this->gdriveEnabled = $status['enabled'];
        $this->gdriveFolderId = $status['folder_id'];
        $this->gdriveClientId = (string) (Setting::getValue('backup.gdrive.oauth_client_id', '')
            ?: config('crm-backup.google_client_id', ''));

        if ($success = session('gdrive_oauth_success')) {
            Notification::make()->title('Google Drive connected')->body((string) $success)->success()->send();
            session()->forget('gdrive_oauth_success');
        }

        if ($error = session('gdrive_oauth_error')) {
            Notification::make()->title('Google sign-in failed')->body((string) $error)->danger()->send();
            session()->forget('gdrive_oauth_error');
        }
    }

    public function createBackup(CrmBackupService $backups, GoogleDriveBackupService $drive, AuditService $audit): void
    {
        try {
            $result = $backups->create();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Backup failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $driveNote = '';

        if ($drive->isReady()) {
            try {
                $upload = $drive->uploadBackup($result['path'], $result['filename']);
                $driveNote = ' Uploaded to Google Drive.';
                $audit->log(
                    action: 'Full Backup Uploaded to Google Drive',
                    newValues: [
                        'filename' => $upload['filename'],
                        'file_id' => $upload['file_id'],
                    ],
                    user: Auth::user(),
                );
            } catch (Throwable $exception) {
                $driveNote = ' Local backup OK; Drive upload failed: '.$exception->getMessage();
            }
        }

        $audit->log(
            action: 'Full Backup Created',
            newValues: [
                'filename' => $result['filename'],
                'size_bytes' => $result['size_bytes'],
                'source' => 'admin_ui',
            ],
            user: Auth::user(),
        );

        Notification::make()
            ->title('Full backup ready')
            ->body(
                $result['filename'].' ('.$backups->formatBytes($result['size_bytes']).').'.$driveNote
                .(
                    ! empty($result['skipped_paths'])
                        ? ' Warning: '.count($result['skipped_paths']).' path(s) skipped due to server permissions — run: chmod -R ug+rwX storage'
                        : ''
                )
            )
            ->success()
            ->send();
    }

    public function deleteBackup(string $filename, CrmBackupService $backups, AuditService $audit): void
    {
        if (! $backups->deleteBackup($filename)) {
            Notification::make()
                ->title('Could not delete backup')
                ->warning()
                ->send();

            return;
        }

        $audit->log(
            action: 'Full Backup Deleted',
            newValues: ['filename' => $filename],
            user: Auth::user(),
        );

        Notification::make()
            ->title('Backup deleted')
            ->success()
            ->send();
    }

    public function uploadLatestToDrive(CrmBackupService $backups, GoogleDriveBackupService $drive, AuditService $audit): void
    {
        $latest = $backups->listBackups()[0] ?? null;

        if (! $latest) {
            Notification::make()
                ->title('No local backup to upload')
                ->warning()
                ->send();

            return;
        }

        try {
            $upload = $drive->uploadBackup($latest['path'], $latest['filename']);
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Google Drive upload failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $audit->log(
            action: 'Full Backup Uploaded to Google Drive',
            newValues: [
                'filename' => $upload['filename'],
                'file_id' => $upload['file_id'],
                'source' => 'admin_ui_manual',
            ],
            user: Auth::user(),
        );

        Notification::make()
            ->title('Uploaded to Google Drive')
            ->body($upload['filename'])
            ->success()
            ->send();
    }

    public function saveGoogleDriveSettings(GoogleDriveBackupService $drive, AuditService $audit): void
    {
        $secretUpdated = $this->gdriveClientSecret !== '';

        try {
            $drive->saveSettings([
                'enabled' => $this->gdriveEnabled,
                'folder_id' => $this->gdriveFolderId,
                'oauth_client_id' => $this->gdriveClientId !== '' ? $this->gdriveClientId : null,
                'oauth_client_secret' => $secretUpdated ? $this->gdriveClientSecret : null,
            ]);
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Could not save Google Drive settings')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $this->gdriveClientSecret = '';

        $audit->log(
            action: 'Google Drive Backup Settings Updated',
            newValues: [
                'enabled' => $this->gdriveEnabled,
                'folder_id' => $this->gdriveFolderId,
                'client_id_saved' => $this->gdriveClientId !== '',
                'client_secret_updated' => $secretUpdated,
            ],
            user: Auth::user(),
        );

        Notification::make()
            ->title('Google Drive settings saved')
            ->body('Next: Sign in with Google, then Test connection.')
            ->success()
            ->send();
    }

    public function testGoogleDrive(GoogleDriveBackupService $drive): void
    {
        try {
            if ($this->gdriveFolderId !== '' || $this->gdriveClientId !== '' || $this->gdriveClientSecret !== '') {
                $drive->saveSettings([
                    'enabled' => $this->gdriveEnabled,
                    'folder_id' => $this->gdriveFolderId,
                    'oauth_client_id' => $this->gdriveClientId !== '' ? $this->gdriveClientId : null,
                    'oauth_client_secret' => $this->gdriveClientSecret !== '' ? $this->gdriveClientSecret : null,
                ]);
                $this->gdriveClientSecret = '';
            }

            $message = $drive->testConnection();
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Google Drive test failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        Notification::make()
            ->title('Google Drive OK')
            ->body($message)
            ->success()
            ->send();
    }

    public function disconnectGoogleDrive(GoogleDriveBackupService $drive, AuditService $audit): void
    {
        $drive->clearCredentials();
        $this->gdriveEnabled = false;
        $this->gdriveFolderId = '';
        $this->gdriveClientSecret = '';

        $audit->log(
            action: 'Google Drive Backup Disconnected',
            user: Auth::user(),
        );

        Notification::make()
            ->title('Google Drive disconnected')
            ->success()
            ->send();
    }

    public function restoreFromUpload(CrmBackupService $backups, AuditService $audit): void
    {
        if (! $this->restoreConfirmed) {
            Notification::make()
                ->title('Confirm restore')
                ->body('Tick the confirmation box — restore replaces all current data and files.')
                ->warning()
                ->send();

            return;
        }

        if (! $this->restoreUpload) {
            Notification::make()
                ->title('Choose a backup zip')
                ->warning()
                ->send();

            return;
        }

        $originalName = $this->restoreUpload->getClientOriginalName();

        if (! preg_match('/^school-crm-full-backup-[\w\-]+\.zip$/i', $originalName)) {
            Notification::make()
                ->title('Invalid file name')
                ->body('Upload a file named like school-crm-full-backup-YYYY-mm-dd_His.zip')
                ->danger()
                ->send();

            return;
        }

        File::ensureDirectoryExists(storage_path('app/private/.restore-upload'));
        $storedPath = storage_path('app/private/.restore-upload/'.$originalName);

        try {
            $this->restoreUpload->storeAs('.restore-upload', $originalName, 'local');
            $storedPath = storage_path('app/private/.restore-upload/'.$originalName);

            if (! is_file($storedPath)) {
                Notification::make()
                    ->title('Upload failed')
                    ->body('Could not store the uploaded backup on the server.')
                    ->danger()
                    ->send();

                return;
            }

            $inspection = $backups->inspectBackupZip($storedPath);

            if (! $inspection['valid'] || ! $inspection['app_key_matches']) {
                Notification::make()
                    ->title('Cannot restore this zip')
                    ->body($inspection['message'])
                    ->danger()
                    ->send();

                return;
            }

            $result = $backups->restore($storedPath, force: true);

            Artisan::call('storage:link');
            Artisan::call('crm:publish-assets');
            Artisan::call('cache:clear');

            try {
                Artisan::call('permission:cache-reset');
            } catch (Throwable) {
            }

            $audit->log(
                action: 'Full Backup Restored',
                newValues: [
                    'filename' => $originalName,
                    'source' => 'admin_ui_upload',
                    'created_at' => $result['manifest']['created_at'] ?? null,
                    'private_files' => $result['private_files'],
                    'public_files' => $result['public_files'],
                ],
                user: Auth::user(),
            );
        } catch (Throwable $exception) {
            Notification::make()
                ->title('Restore failed')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        } finally {
            $this->restoreUpload = null;
            $this->restoreConfirmed = false;

            if (isset($storedPath) && is_file($storedPath) && str_contains($storedPath, '.restore-upload')) {
                @unlink($storedPath);
            }
        }

        Notification::make()
            ->title('Restore complete')
            ->body('Database and files were restored. Refresh the page. Restart the queue worker if it is running.')
            ->success()
            ->send();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.backups')
                ->viewData(fn (): array => [
                    'backups' => app(CrmBackupService::class)->listBackups(),
                    'retain' => (int) config('crm-backup.retain', 14),
                    'scheduleAt' => (string) config('crm-backup.schedule_at', '02:15'),
                    'formatBytes' => fn (int $bytes): string => app(CrmBackupService::class)->formatBytes($bytes),
                    'drive' => app(GoogleDriveBackupService::class)->status(),
                    'gdriveEnabled' => $this->gdriveEnabled,
                    'gdriveFolderId' => $this->gdriveFolderId,
                    'restoreConfirmed' => $this->restoreConfirmed,
                ]),
        ]);
    }
}
