<?php

namespace App\Console\Commands;

use App\Models\AcademicSession;
use Database\Seeders\DemoBaselineSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;

class CrmSeedDemoCommand extends Command
{
    protected $signature = 'crm:seed-demo
                            {--baseline : Also run baseline seeders (roles, courses, exam types) if missing}';

    protected $description = 'Load demo students, fees, and exam marks (without wiping the database)';

    public function handle(): int
    {
        if ($this->option('baseline') || ! AcademicSession::query()->exists()) {
            $this->info('Loading baseline data…');
            $this->call('db:seed', ['--class' => DemoBaselineSeeder::class, '--force' => true]);
        }

        if (! AcademicSession::current()) {
            $this->error('No current academic session. Run Academic Sessions and mark one as current, or use crm:reset-demo.');

            return self::FAILURE;
        }

        $this->info('Loading demo data…');
        $this->call('db:seed', ['--class' => DemoDataSeeder::class, '--force' => true]);

        $this->newLine();
        $this->info('Demo data loaded. See terminal output above for login and roll numbers.');

        return self::SUCCESS;
    }
}
