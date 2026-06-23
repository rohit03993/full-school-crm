<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('enrollments')) {
            return;
        }

        if ($this->uniqueIndexExists('enrollments', 'enrollments_admission_id_unique')) {
            return;
        }

        Schema::table('enrollments', function (Blueprint $table) {
            $table->unique('admission_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('enrollments')) {
            return;
        }

        if (! $this->uniqueIndexExists('enrollments', 'enrollments_admission_id_unique')) {
            return;
        }

        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropUnique(['admission_id']);
        });
    }

    protected function uniqueIndexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $indexes = $connection->select('SHOW INDEX FROM `'.$table.'` WHERE Key_name = ?', [$indexName]);

        return count($indexes) > 0;
    }
};
