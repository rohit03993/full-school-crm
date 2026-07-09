<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('result_declarations')) {
            Schema::table('result_declarations', function (Blueprint $table): void {
                if (! Schema::hasColumn('result_declarations', 'marks_locked_at')) {
                    $table->timestamp('marks_locked_at')->nullable()->after('declared_at');
                }
                if (! Schema::hasColumn('result_declarations', 'marks_locked_by_user_id')) {
                    $table->foreignId('marks_locked_by_user_id')->nullable()->after('marks_locked_at')
                        ->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('result_declarations', 'unpublished_at')) {
                    $table->timestamp('unpublished_at')->nullable()->after('marks_locked_by_user_id');
                }
                if (! Schema::hasColumn('result_declarations', 'unpublished_by_user_id')) {
                    $table->foreignId('unpublished_by_user_id')->nullable()->after('unpublished_at')
                        ->constrained('users')->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('student_marksheets')) {
            Schema::table('student_marksheets', function (Blueprint $table): void {
                if (! Schema::hasColumn('student_marksheets', 'rank')) {
                    $table->unsignedSmallInteger('rank')->nullable()->after('division');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('student_marksheets') && Schema::hasColumn('student_marksheets', 'rank')) {
            Schema::table('student_marksheets', function (Blueprint $table): void {
                $table->dropColumn('rank');
            });
        }

        if (Schema::hasTable('result_declarations')) {
            Schema::table('result_declarations', function (Blueprint $table): void {
                if (Schema::hasColumn('result_declarations', 'unpublished_by_user_id')) {
                    $table->dropConstrainedForeignId('unpublished_by_user_id');
                }
                if (Schema::hasColumn('result_declarations', 'unpublished_at')) {
                    $table->dropColumn('unpublished_at');
                }
                if (Schema::hasColumn('result_declarations', 'marks_locked_by_user_id')) {
                    $table->dropConstrainedForeignId('marks_locked_by_user_id');
                }
                if (Schema::hasColumn('result_declarations', 'marks_locked_at')) {
                    $table->dropColumn('marks_locked_at');
                }
            });
        }
    }
};
