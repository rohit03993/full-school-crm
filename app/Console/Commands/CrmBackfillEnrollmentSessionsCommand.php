<?php

namespace App\Console\Commands;

use App\Models\AcademicSession;
use App\Models\Enrollment;
use App\Services\CrmDashboardService;
use Illuminate\Console\Command;

class CrmBackfillEnrollmentSessionsCommand extends Command
{
    protected $signature = 'crm:backfill-enrollment-sessions {--dry-run : Show how many rows would change without updating}';

    protected $description = 'Set academic_session_id on active enrollments that are missing it (uses current session).';

    public function handle(): int
    {
        $sessionId = AcademicSession::current()?->id;

        if (! $sessionId) {
            $this->error('No current academic session is configured. Set one under Academics → Sessions first.');

            return self::FAILURE;
        }

        $query = Enrollment::query()
            ->where('is_active', true)
            ->whereNull('academic_session_id');

        $count = (clone $query)->count();

        if ($count === 0) {
            $this->info('No active enrollments need a session backfill.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("Would update {$count} enrollment(s) to academic_session_id={$sessionId}.");

            return self::SUCCESS;
        }

        $updated = $query->update(['academic_session_id' => $sessionId]);

        $this->info("Updated {$updated} enrollment(s) with the current academic session.");

        CrmDashboardService::flushAllCaches();

        return self::SUCCESS;
    }
}
