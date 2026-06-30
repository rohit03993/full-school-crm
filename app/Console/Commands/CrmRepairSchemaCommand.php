<?php

namespace App\Console\Commands;

use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

class CrmRepairSchemaCommand extends Command
{
    protected $signature = 'crm:repair-schema {--force : Run without confirmation}';

    protected $description = 'Repair missing activity/result tables after upgrading CRM schema';

    public function handle(): int
    {
        $this->components->info('Checking CRM database schema…');

        $missing = $this->missingTables();

        if ($missing !== []) {
            $this->components->warn('Missing tables: '.implode(', ', $missing));
        } else {
            $this->components->info('Core tables look present.');
        }

        if (! $this->option('force') && ! $this->confirm('Run pending migrations and seed activity types if needed?', true)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $migrate = Artisan::call('migrate', ['--force' => true]);
        $this->output->write(Artisan::output());

        if ($migrate !== 0) {
            $this->components->error('Migration failed. Check storage/logs/laravel.log');

            return self::FAILURE;
        }

        if (Schema::hasTable('activity_types')) {
            $this->call('db:seed', ['--class' => ActivityTypeSeeder::class, '--force' => true]);
        }

        Artisan::call('optimize:clear');
        $this->components->success('Schema repair complete.');

        $stillMissing = $this->missingTables();

        if ($stillMissing !== []) {
            $this->components->error('Still missing: '.implode(', ', $stillMissing));

            return self::FAILURE;
        }

        $this->line('Tests & Exams (/admin/activity-sessions) should load now.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    protected function missingTables(): array
    {
        $required = [
            'activity_types',
            'activity_sessions',
            'activity_attendances',
            'result_declarations',
            'student_marksheets',
            'marksheet_serial_sequences',
        ];

        return array_values(array_filter(
            $required,
            fn (string $table): bool => ! Schema::hasTable($table),
        ));
    }
}
