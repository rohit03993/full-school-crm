<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_calls')) {
            Schema::create('student_calls', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('enquiry_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('call_status');
                $table->string('call_direction')->default('outgoing');
                $table->string('who_answered')->nullable();
                $table->unsignedSmallInteger('duration_minutes')->nullable();
                $table->text('call_notes')->nullable();
                $table->json('tags')->nullable();
                $table->string('visit_status_changed_to')->nullable();
                $table->timestamp('next_followup_at')->nullable();
                $table->dateTime('called_at');
                $table->timestamps();

                $table->index(['student_id', 'called_at']);
                $table->index(['user_id', 'called_at']);
                $table->index('enquiry_id');
            });
        }

        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'total_calls')) {
                $table->unsignedInteger('total_calls')->default(0);
            }

            if (! Schema::hasColumn('students', 'last_call_at')) {
                $table->timestamp('last_call_at')->nullable();
            }

            if (! Schema::hasColumn('students', 'last_call_status')) {
                $table->string('last_call_status')->nullable();
            }

            if (! Schema::hasColumn('students', 'last_call_notes')) {
                $table->text('last_call_notes')->nullable();
            }

            if (! Schema::hasColumn('students', 'next_call_followup_at')) {
                $table->timestamp('next_call_followup_at')->nullable();
            }

            if (! Schema::hasColumn('students', 'is_call_blocked')) {
                $table->boolean('is_call_blocked')->default(false);
            }

            if (! Schema::hasColumn('students', 'call_blocked_reason')) {
                $table->string('call_blocked_reason')->nullable();
            }

            if (! Schema::hasColumn('students', 'call_blocked_at')) {
                $table->timestamp('call_blocked_at')->nullable();
            }
        });

        if (
            Schema::hasColumn('students', 'is_call_blocked')
            && Schema::hasColumn('students', 'next_call_followup_at')
            && ! $this->indexExists('students', 'students_call_followup_idx')
        ) {
            Schema::table('students', function (Blueprint $table): void {
                $table->index(['is_call_blocked', 'next_call_followup_at'], 'students_call_followup_idx');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('students', 'students_call_followup_idx')) {
            Schema::table('students', function (Blueprint $table): void {
                $table->dropIndex('students_call_followup_idx');
            });
        }

        Schema::table('students', function (Blueprint $table) {
            $columns = array_filter([
                Schema::hasColumn('students', 'total_calls') ? 'total_calls' : null,
                Schema::hasColumn('students', 'last_call_at') ? 'last_call_at' : null,
                Schema::hasColumn('students', 'last_call_status') ? 'last_call_status' : null,
                Schema::hasColumn('students', 'last_call_notes') ? 'last_call_notes' : null,
                Schema::hasColumn('students', 'next_call_followup_at') ? 'next_call_followup_at' : null,
                Schema::hasColumn('students', 'is_call_blocked') ? 'is_call_blocked' : null,
                Schema::hasColumn('students', 'call_blocked_reason') ? 'call_blocked_reason' : null,
                Schema::hasColumn('students', 'call_blocked_at') ? 'call_blocked_at' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::dropIfExists('student_calls');
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return DB::select(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$table, $indexName]
        ) !== [];
    }
};
