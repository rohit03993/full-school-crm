<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('trainer_user_id')->constrained('users')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->string('status');
            $table->timestamps();

            $table->index(['course_id', 'status']);
        });

        Schema::create('batch_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->boolean('is_active')->default(true);
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'is_active']);
            $table->index(['batch_id', 'is_active']);
        });

        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->date('attendance_date');
            $table->string('status');
            $table->foreignId('marked_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['batch_id', 'student_id', 'attendance_date']);
            $table->index(['batch_id', 'attendance_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
        Schema::dropIfExists('batch_students');
        Schema::dropIfExists('batches');
    }
};
