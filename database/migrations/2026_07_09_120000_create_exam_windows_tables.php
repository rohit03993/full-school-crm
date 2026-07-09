<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exam_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_type_id')->constrained()->restrictOnDelete();
            $table->string('test_name');
            $table->date('session_date');
            $table->string('test_key')->index();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'test_key']);
        });

        Schema::create('exam_window_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_window_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('max_marks')->default(100);
            $table->foreignId('activity_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('entered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('marks_entered_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_window_id', 'course_subject_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exam_window_subjects');
        Schema::dropIfExists('exam_windows');
    }
};
