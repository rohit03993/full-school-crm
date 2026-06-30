<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs servers where 2026_06_20_100001_create_activity_tables was already
 * recorded as migrated under an older schema (no activity_types table).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_types')) {
            Schema::create('activity_types', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('plural_name');
                $table->string('slug')->unique();
                $table->string('icon')->nullable();
                $table->text('description')->nullable();
                $table->json('field_schema')->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('activity_sessions')) {
            Schema::create('activity_sessions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('activity_type_id')->constrained()->restrictOnDelete();
                $table->string('title');
                $table->date('session_date');
                $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
                $table->json('metadata')->nullable();
                $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
                $table->timestamps();

                $table->index(['activity_type_id', 'session_date']);
                $table->index(['batch_id', 'session_date']);
            });
        }

        if (! Schema::hasTable('activity_attendances')) {
            Schema::create('activity_attendances', function (Blueprint $table) {
                $table->id();
                $table->morphs('attendable');
                $table->foreignId('student_id')->constrained()->restrictOnDelete();
                $table->boolean('is_present')->default(false);
                $table->foreignId('marked_by_user_id')->constrained('users')->restrictOnDelete();
                $table->timestamps();

                $table->unique(['attendable_type', 'attendable_id', 'student_id'], 'activity_attendances_unique');
            });
        }

        if (Schema::hasTable('activity_attendances') && ! Schema::hasColumn('activity_attendances', 'marks_obtained')) {
            Schema::table('activity_attendances', function (Blueprint $table) {
                $table->decimal('marks_obtained', 8, 2)->nullable()->after('is_present');
                $table->string('grade', 10)->nullable()->after('marks_obtained');
                $table->string('remarks')->nullable()->after('grade');
            });
        }
    }

    public function down(): void
    {
        // Intentionally empty — repair migration only.
    }
};
