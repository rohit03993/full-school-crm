<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('enquiries', 'enquiries_created_at_index', function (Blueprint $table): void {
            $table->index('created_at');
        });

        $this->addIndexIfMissing('enquiries', 'enquiries_meeting_with_user_id_index', function (Blueprint $table): void {
            $table->index('meeting_with_user_id');
        });

        $this->addIndexIfMissing('enquiries', 'enquiries_latest_visit_status_index', function (Blueprint $table): void {
            $table->index('latest_visit_status');
        });

        $this->addIndexIfMissing('visits', 'visits_next_follow_up_date_index', function (Blueprint $table): void {
            $table->index('next_follow_up_date');
        });

        $this->addIndexIfMissing('admissions', 'admissions_status_index', function (Blueprint $table): void {
            $table->index('status');
        });
    }

    public function down(): void
    {
        $this->dropIndexIfPresent('enquiries', 'enquiries_created_at_index');
        $this->dropIndexIfPresent('enquiries', 'enquiries_meeting_with_user_id_index');
        $this->dropIndexIfPresent('enquiries', 'enquiries_latest_visit_status_index');
        $this->dropIndexIfPresent('visits', 'visits_next_follow_up_date_index');
        $this->dropIndexIfPresent('admissions', 'admissions_status_index');
    }

    private function addIndexIfMissing(string $table, string $indexName, callable $callback): void
    {
        if ($this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, $callback);
    }

    private function dropIndexIfPresent(string $table, string $indexName): void
    {
        if (! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $table) use ($indexName): void {
            $table->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select('PRAGMA index_list('.DB::getPdo()->quote($table).')');

            return collect($indexes)->contains(
                fn (object $index): bool => ($index->name ?? null) === $indexName,
            );
        }

        return DB::select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$table, $indexName]
        ) !== [];
    }
};
