<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('standard_course_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_subject_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('ready_at')->nullable();
            $table->foreignId('ready_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['academic_session_id', 'course_id', 'course_subject_id'],
                'standard_course_plans_session_course_subject_unique',
            );
        });

        Schema::create('standard_course_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standard_course_plan_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('standard_course_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standard_course_chapter_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('planned_minutes');
            $table->string('reference_book')->nullable();
            $table->unsignedSmallInteger('dpp_count')->default(0);
            $table->unsignedSmallInteger('quiz_count')->default(0);
            $table->unsignedSmallInteger('test_count')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standard_course_topics');
        Schema::dropIfExists('standard_course_chapters');
        Schema::dropIfExists('standard_course_plans');
    }
};
