<?php

namespace App\Console\Commands;

use App\Services\CrmPermissionSyncService;
use Illuminate\Console\Command;

class SyncCrmPermissions extends Command
{
    protected $signature = 'crm:sync-permissions';

    protected $description = 'Create CRM job roles and permissions (safe to re-run)';

    public function handle(CrmPermissionSyncService $sync): int
    {
        $result = $sync->sync();

        $this->info('CRM permissions synced.');
        $this->line("  Permissions: {$result['permissions']}");
        $this->line("  Job roles: {$result['job_roles']}");

        return self::SUCCESS;
    }
}
