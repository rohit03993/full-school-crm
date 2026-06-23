<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CrmResetAppCommand extends Command
{
    protected $signature = 'crm:reset-app
                            {--force : Skip confirmation prompt}';

    protected $description = 'Wipe all CRM database data (migrate:fresh, no seeders)';

    public function handle(): int
    {
        $this->warn('This deletes ALL students, leads, courses, fees, imports, and settings stored in the database.');
        $this->line('Migrations are re-run on an empty database. No seeders are executed.');

        if (! $this->option('force') && ! $this->confirm('Reset the entire CRM database now?', false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $this->call('migrate:fresh', ['--force' => true]);

        $this->newLine();
        $this->info('Database reset complete — empty CRM, no demo data.');
        $this->newLine();
        $this->line('Run these next:');
        $this->line('  php artisan crm:reset-demo --force   (reset + demo data for learning)');
        $this->line('  php artisan crm:ensure-admin');
        $this->line('  php artisan config:clear');
        $this->newLine();
        $this->line('Then log in at /admin, complete First Run Setup, and add only the courses you need.');

        return self::SUCCESS;
    }
}
