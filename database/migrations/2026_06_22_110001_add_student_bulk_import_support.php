<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_import_batches')) {
            Schema::create('student_import_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
                $table->foreignId('course_id')->constrained()->restrictOnDelete();
                $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
                $table->string('original_filename');
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('created_count')->default(0);
                $table->unsignedInteger('updated_count')->default(0);
                $table->unsignedInteger('skipped_count')->default(0);
                $table->unsignedInteger('failed_count')->default(0);
                $table->json('error_rows')->nullable();
                $table->string('status')->default('completed');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('enrollments') && ! Schema::hasColumn('enrollments', 'academic_session_id')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->foreignId('academic_session_id')
                    ->nullable()
                    ->after('course_id')
                    ->constrained()
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('admissions') && ! Schema::hasColumn('admissions', 'import_batch_id')) {
            Schema::table('admissions', function (Blueprint $table) {
                $table->foreignId('import_batch_id')
                    ->nullable()
                    ->after('enquiry_id')
                    ->constrained('student_import_batches')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('admissions') && Schema::hasColumn('admissions', 'import_batch_id')) {
            Schema::table('admissions', function (Blueprint $table) {
                $table->dropConstrainedForeignId('import_batch_id');
            });
        }

        if (Schema::hasTable('enrollments') && Schema::hasColumn('enrollments', 'academic_session_id')) {
            Schema::table('enrollments', function (Blueprint $table) {
                $table->dropConstrainedForeignId('academic_session_id');
            });
        }

        Schema::dropIfExists('student_import_batches');
    }
};
