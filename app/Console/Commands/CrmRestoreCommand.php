<?php

namespace App\Console\Commands;

use App\Services\AuditService;
use App\Services\CrmBackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Throwable;

class CrmRestoreCommand extends Command
{
    protected $signature = 'crm:restore
                            {path : Absolute or relative path to a school-crm-full-backup-*.zip}
                            {--force : Required. Confirms you accept wiping current DB + storage files}';

    protected $description = 'Restore a full CRM backup zip (destructive: replaces database and uploaded files).';

    public function handle(CrmBackupService $backups, AuditService $audit): int
    {
        if (! $this->option('force')) {
            $this->error('Refusing to restore without --force (this replaces the database and all uploaded files).');

            return self::FAILURE;
        }

        $path = $this->argument('path');

        if (! preg_match('#^([A-Za-z]:[\\\\/]|/|\\\\\\\\)#', $path)) {
            $candidate = base_path($path);

            if (is_file($candidate)) {
                $path = $candidate;
            }
        }

        if (! is_file($path)) {
            $resolved = $backups->findBackup(basename($path));
            $path = $resolved ?? $path;
        }

        if (! is_file($path)) {
            $this->error('Backup file not found: '.$this->argument('path'));

            return self::FAILURE;
        }

        $this->warn('Restoring FULL backup — current database and files will be replaced.');
        $this->line($path);

        try {
            $result = $backups->restore($path, force: true);
        } catch (Throwable $exception) {
            $this->error('Restore failed: '.$exception->getMessage());

            return self::FAILURE;
        }

        Artisan::call('storage:link');
        Artisan::call('crm:publish-assets');
        Artisan::call('cache:clear');

        try {
            Artisan::call('permission:cache-reset');
        } catch (Throwable) {
            // optional if package command unavailable
        }

        $audit->log(
            action: 'Full Backup Restored',
            newValues: [
                'path' => $path,
                'created_at' => $result['manifest']['created_at'] ?? null,
                'private_files' => $result['private_files'],
                'public_files' => $result['public_files'],
            ],
        );

        $this->info('Restore complete.');
        $this->line('Private files restored: '.$result['private_files']);
        $this->line('Public files restored: '.$result['public_files']);
        $this->comment('Restart the queue worker if it is running.');

        return self::SUCCESS;
    }
}
