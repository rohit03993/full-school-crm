<?php

namespace App\Console\Commands;

use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class CrmEnsureAdminCommand extends Command
{
    protected $signature = 'crm:ensure-admin';

    protected $description = 'Create or reset the Super Admin account and verify storage is writable';

    public function handle(): int
    {
        $sessionPath = storage_path('framework/sessions');
        foreach ([$sessionPath, base_path('bootstrap/cache'), storage_path('logs'), storage_path('app')] as $path) {
            if (! is_writable($path)) {
                $this->error("Not writable: {$path}");
                $this->line('Run: chown -R www-data:www-data '.base_path());
                $this->line('Run: chmod -R 775 storage bootstrap/cache');

                return self::FAILURE;
            }
        }

        if (! File::isDirectory($sessionPath)) {
            File::makeDirectory($sessionPath, 0755, true);
        }

        if (! File::exists(public_path('storage'))) {
            $this->warn('public/storage is missing — logos and uploaded images will not load.');
            $this->call('storage:link');
        }

        $this->call('crm:publish-assets');

        $this->call('crm:sync-permissions');
        $this->call('db:seed', ['--class' => RoleSeeder::class, '--force' => true]);
        $this->call('db:seed', ['--class' => AdminUserSeeder::class, '--force' => true]);

        $mobile = env('ADMIN_MOBILE', '9876543210');
        $this->newLine();
        $this->info('Admin login URL: '.url('/admin'));
        $this->line("Mobile: {$mobile}");
        $this->line('Password: value of ADMIN_PASSWORD in .env (default Admin@2026)');
        $this->newLine();
        $this->call('config:clear');

        return self::SUCCESS;
    }
}
