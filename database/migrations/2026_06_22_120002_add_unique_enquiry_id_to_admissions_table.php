<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admissions')) {
            return;
        }

        if ($this->uniqueIndexExists('admissions', 'admissions_enquiry_id_unique')) {
            return;
        }

        Schema::table('admissions', function (Blueprint $table) {
            $table->unique('enquiry_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('admissions')) {
            return;
        }

        if (! $this->uniqueIndexExists('admissions', 'admissions_enquiry_id_unique')) {
            return;
        }

        Schema::table('admissions', function (Blueprint $table) {
            $table->dropUnique(['enquiry_id']);
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
