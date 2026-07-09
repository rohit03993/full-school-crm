<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentDataResetService;
use Illuminate\Console\Command;

class CrmResetStudentsCommand extends Command
{
    protected $signature = 'crm:reset-students
                            {--force : Skip confirmation prompt}';

    protected $description = 'Delete all student-related data and keep academics structure, staff, and WhatsApp connection';

    public function handle(StudentDataResetService $resetService): int
    {
        $studentCount = Student::withTrashed()->count();

        $this->warn('This permanently deletes ALL student-related data.');
        $this->newLine();
        $this->line('<fg=red>Removed:</>');
        foreach (StudentDataResetService::clearedSummary() as $line) {
            $this->line('  • '.$line);
        }
        $this->newLine();
        $this->line('<fg=green>Kept:</>');
        foreach (StudentDataResetService::preservedSummary() as $line) {
            $this->line('  • '.$line);
        }
        $this->newLine();
        $this->line("Students to remove: {$studentCount}");

        if ($studentCount === 0 && ! $this->option('force')) {
            $this->info('No student records found — nothing to reset.');

            return self::SUCCESS;
        }

        if (! $this->option('force') && ! $this->confirm('Delete all student data now?', false)) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $counts = $resetService->reset();

        $this->newLine();
        $this->info('Student data reset complete.');
        $this->table(
            ['Table', 'Rows removed'],
            collect($counts)
                ->map(fn (int $count, string $table): array => [$table, (string) $count])
                ->values()
                ->all(),
        );

        $this->newLine();
        $this->line('Still available:');
        $this->line('  Courses: '.Course::query()->count());
        $this->line('  Staff users: '.User::query()->whereHas('roles')->count());
        $this->newLine();
        $this->comment('You can now import students from Students → Import.');

        return self::SUCCESS;
    }
}
