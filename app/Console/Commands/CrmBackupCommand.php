<?php

namespace App\Console\Commands;

use App\Services\AuditService;
use App\Services\CrmBackupService;
use App\Services\GoogleDriveBackupService;
use Illuminate\Console\Command;
use Throwable;

class CrmBackupCommand extends Command
{
    protected $signature = 'crm:backup
                            {--keep= : Override how many backup zips to retain}
                            {--skip-drive : Do not upload to Google Drive even if connected}';

    protected $description = 'Create a full CRM backup zip (database + all private/public files + APP_KEY).';

    public function handle(
        CrmBackupService $backups,
        GoogleDriveBackupService $drive,
        AuditService $audit,
    ): int {
        if ($this->option('keep') !== null) {
            config(['crm-backup.retain' => max(1, (int) $this->option('keep'))]);
        }

        $this->info('Starting full School CRM backup…');
        $this->line('Includes: database, documents/photos, receipts, homework, logos, WhatsApp media, settings.');

        try {
            $result = $backups->create(function (string $message): void {
                $this->line('  · '.$message);
            });
        } catch (Throwable $exception) {
            $this->error('Backup failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        $driveUpload = null;

        if (! $this->option('skip-drive') && $drive->isReady()) {
            $this->line('  · Uploading to Google Drive…');

            try {
                $driveUpload = $drive->uploadBackup($result['path'], $result['filename']);
                $this->info('Uploaded to Google Drive: '.$driveUpload['filename']);
            } catch (Throwable $exception) {
                $this->warn('Local backup OK, but Google Drive upload failed: '.$exception->getMessage());
            }
        } elseif (! $this->option('skip-drive')) {
            $this->comment('Google Drive not connected — backup kept on server only. Connect under Setup → Backups.');
        }

        $audit->log(
            action: 'Full Backup Created',
            newValues: [
                'filename' => $result['filename'],
                'size_bytes' => $result['size_bytes'],
                'tables' => $result['tables'],
                'private_files' => $result['private_files'],
                'public_files' => $result['public_files'],
                'google_drive_file_id' => $driveUpload['file_id'] ?? null,
            ],
        );

        $this->newLine();
        $this->info('Backup ready: '.$result['filename']);
        $this->line('Path: '.$result['path']);
        $this->line('Size: '.$backups->formatBytes($result['size_bytes']));
        $this->line('Tables: '.$result['tables'].' · Private files: '.$result['private_files'].' · Public files: '.$result['public_files']);
        $this->comment('Download from Setup → Backups (Super Admin), or open Google Drive if connected.');

        return self::SUCCESS;
    }
}
