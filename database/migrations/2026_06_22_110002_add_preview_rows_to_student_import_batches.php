<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_import_batches')) {
            return;
        }

        if (! Schema::hasColumn('student_import_batches', 'preview_rows')) {
            Schema::table('student_import_batches', function (Blueprint $table) {
                $table->json('preview_rows')->nullable()->after('original_filename');
            });
        }

        if (! Schema::hasColumn('student_import_batches', 'duplicate_resolutions')) {
            Schema::table('student_import_batches', function (Blueprint $table) {
                $table->json('duplicate_resolutions')->nullable()->after('preview_rows');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_import_batches')) {
            return;
        }

        $columns = array_filter([
            Schema::hasColumn('student_import_batches', 'preview_rows') ? 'preview_rows' : null,
            Schema::hasColumn('student_import_batches', 'duplicate_resolutions') ? 'duplicate_resolutions' : null,
        ]);

        if ($columns !== []) {
            Schema::table('student_import_batches', function (Blueprint $table) use ($columns) {
                $table->dropColumn($columns);
            });
        }
    }
};
