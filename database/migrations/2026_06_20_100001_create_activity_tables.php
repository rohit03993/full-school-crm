<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('practical_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->date('session_date');
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['batch_id', 'session_date']);
        });

        Schema::create('industrial_visits', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('location')->nullable();
            $table->date('visit_date');
            $table->text('description')->nullable();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['batch_id', 'visit_date']);
        });

        Schema::create('seminars', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->string('title');
            $table->date('seminar_date');
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['batch_id', 'seminar_date']);
        });

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

    public function down(): void
    {
        Schema::dropIfExists('activity_attendances');
        Schema::dropIfExists('seminars');
        Schema::dropIfExists('industrial_visits');
        Schema::dropIfExists('practical_sessions');
    }
};
