<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_types')) {
            throw new \RuntimeException(
                'The activity_types table is missing. Run: php artisan crm:repair-schema --force',
            );
        }

        if (Schema::hasTable('result_declarations')
            && Schema::hasTable('student_marksheets')
            && Schema::hasTable('marksheet_serial_sequences')) {
            return;
        }

        if (! Schema::hasTable('result_declarations')) {
            Schema::create('result_declarations', function (Blueprint $table) {
                $table->id();
                $table->string('group_key')->unique();
                $table->string('test_name');
                $table->date('session_date');
                $table->foreignId('batch_id')->constrained()->restrictOnDelete();
                $table->foreignId('activity_type_id')->constrained()->restrictOnDelete();
                $table->string('status')->default('draft');
                $table->date('declaration_date')->nullable();
                $table->foreignId('declared_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('declared_at')->nullable();
                $table->date('marksheet_issue_date')->nullable();
                $table->foreignId('marksheet_issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('marksheet_issued_at')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['batch_id', 'session_date']);
                $table->index('status');
            });
        }

        if (! Schema::hasTable('marksheet_serial_sequences')) {
            Schema::create('marksheet_serial_sequences', function (Blueprint $table) {
                $table->unsignedTinyInteger('id')->primary();
                $table->unsignedInteger('last_value')->default(0);
            });

            DB::table('marksheet_serial_sequences')->insert(['id' => 1, 'last_value' => 0]);
        }

        if (! Schema::hasTable('student_marksheets')) {
            Schema::create('student_marksheets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('result_declaration_id')->constrained()->cascadeOnDelete();
                $table->foreignId('student_id')->constrained()->restrictOnDelete();
                $table->unsignedInteger('marksheet_serial')->unique();
                $table->decimal('total_obtained', 10, 2)->nullable();
                $table->decimal('total_max', 10, 2)->nullable();
                $table->decimal('percentage', 5, 2)->nullable();
                $table->string('division')->nullable();
                $table->string('pdf_path')->nullable();
                $table->json('snapshot');
                $table->timestamps();

                $table->unique(['result_declaration_id', 'student_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_marksheets');
        Schema::dropIfExists('marksheet_serial_sequences');
        Schema::dropIfExists('result_declarations');
    }
};
