<?php

namespace App\Console\Commands;

use App\Services\AuditService;
use App\Services\CrmBackupService;
use Illuminate\Console\Command;
use Throwable;

class CrmBackupCommand extends Command
{
    protected $signature = 'crm:backup
                            {--keep= : Override how many backup zips to retain}';

    protected $description = 'Create a full CRM backup zip (database + all private/public files + APP_KEY).';

    public function handle(CrmBackupService $backups, AuditService $audit): int
    {
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

        $audit->log(
            action: 'Full Backup Created',
            newValues: [
                'filename' => $result['filename'],
                'size_bytes' => $result['size_bytes'],
                'tables' => $result['tables'],
                'private_files' => $result['private_files'],
                'public_files' => $result['public_files'],
            ],
        );

        $this->newLine();
        $this->info('Backup ready: '.$result['filename']);
        $this->line('Path: '.$result['path']);
        $this->line('Size: '.$backups->formatBytes($result['size_bytes']));
        $this->line('Tables: '.$result['tables'].' · Private files: '.$result['private_files'].' · Public files: '.$result['public_files']);
        $this->comment('Download from Setup → Backups (Super Admin), or copy the zip off the server.');

        return self::SUCCESS;
    }
}
