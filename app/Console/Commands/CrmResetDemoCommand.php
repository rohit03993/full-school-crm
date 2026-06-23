<?php

namespace App\Console\Commands;

use Database\Seeders\DemoBaselineSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;

class CrmResetDemoCommand extends Command
{
    protected $signature = 'crm:reset-demo
                            {--force : Skip confirmation prompt}';

    protected $description = 'Reset database and load demo data (courses, students, fees, exam marks)';

    public function handle(): int
    {
        $this->warn('This wipes the database and loads demo data for learning the CRM.');
        $this->line('Includes: leads, admissions, enrolled students, fees, unit test marks, exam types.');

        if (! $this->option('force') && ! $this->confirm('Reset and load demo data now?', false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $this->call('migrate:fresh', ['--force' => true]);

        $this->info('Loading baseline data…');
        $this->call('db:seed', ['--class' => DemoBaselineSeeder::class, '--force' => true]);

        $this->info('Loading demo students, fees, and marks…');
        $this->call('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true]);

        $this->call('crm:ensure-admin');

        $this->newLine();
        $this->info('Demo CRM ready.');
        $this->newLine();
        $this->line('Log in at /admin');
        $this->line('  Super Admin: '.env('ADMIN_EMAIL', 'rohit03993@gmail.com').' / '.env('ADMIN_PASSWORD', 'Admin@2026'));
        $this->line('  Staff: demo@example.com / password');
        $this->newLine();
        $this->line('Explore:');
        $this->line('  Student profile → Exam tab — June & July tests, subjects in columns');
        $this->line('  Academics → Import Marks — bulk upload rolls 101–103');
        $this->line('  Students — Rohit (101), Sneha (102), Amit (103) in Class 12-A');

        return self::SUCCESS;
    }
}
