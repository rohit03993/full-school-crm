<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('section_course_plans')) {
        Schema::create('section_course_plans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('standard_course_plan_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('academic_session_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->foreignId('course_subject_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('lecture_minutes')->default(60);
            $table->string('status', 20)->default('draft');
            $table->unsignedSmallInteger('version')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_comment')->nullable();
            $table->text('change_reason')->nullable();
            $table->foreignId('copied_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['batch_id', 'course_subject_id'], 'section_plan_batch_subject_unique');
        });
        }

        if (! Schema::hasTable('section_course_chapters')) {
        Schema::create('section_course_chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_course_plan_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedSmallInteger('estimated_marks')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('section_course_topics')) {
        Schema::create('section_course_topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_course_chapter_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('planned_minutes')->default(0);
            $table->string('reference_book')->nullable();
            $table->unsignedSmallInteger('dpp_count')->default(0);
            $table->unsignedSmallInteger('quiz_count')->default(0);
            $table->unsignedSmallInteger('test_count')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('section_course_practicals')) {
        Schema::create('section_course_practicals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_course_plan_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('kind', 20)->default('experiment');
            $table->unsignedInteger('planned_minutes')->nullable();
            $table->unsignedSmallInteger('estimated_marks')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        }

        if (! Schema::hasTable('section_course_plan_versions')) {
        Schema::create('section_course_plan_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_course_plan_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->unsignedSmallInteger('lecture_minutes')->default(60);
            $table->text('change_reason')->nullable();
            $table->json('snapshot');
            $table->foreignId('submitted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('finalized_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('finalized_at')->nullable();
            $table->timestamps();

            $table->unique(['section_course_plan_id', 'version'], 'section_plan_version_unique');
        });
        } elseif (! $this->hasUniqueColumns('section_course_plan_versions', ['section_course_plan_id', 'version'])) {
            Schema::table('section_course_plan_versions', function (Blueprint $table) {
                $table->unique(['section_course_plan_id', 'version'], 'section_plan_version_unique');
            });
        }
    }

    /**
     * @param  list<string>  $columns
     */
    private function hasUniqueColumns(string $table, array $columns): bool
    {
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['unique'] ?? false) && ($index['columns'] ?? []) === $columns) {
                return true;
            }
        }

        return false;
    }

    public function down(): void
    {
        Schema::dropIfExists('section_course_plan_versions');
        Schema::dropIfExists('section_course_practicals');
        Schema::dropIfExists('section_course_topics');
        Schema::dropIfExists('section_course_chapters');
        Schema::dropIfExists('section_course_plans');
    }
};
