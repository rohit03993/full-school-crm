<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_subject_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['batch_id', 'course_subject_id']);
            $table->index(['batch_id', 'sort_order']);
        });

        // Preserve current behaviour on upgrade: every existing section starts with all
        // subjects currently defined for its programme. Staff/homework/exam FKs stay unchanged.
        DB::table('batches')
            ->select(['id', 'course_id'])
            ->whereNotNull('course_id')
            ->orderBy('id')
            ->get()
            ->each(function (object $batch): void {
                $subjects = DB::table('course_subjects')
                    ->where('course_id', $batch->course_id)
                    ->where('is_active', true)
                    ->orderBy('sort_order')
                    ->orderBy('name')
                    ->get(['id']);

                foreach ($subjects as $index => $subject) {
                    DB::table('batch_subjects')->insertOrIgnore([
                        'batch_id' => $batch->id,
                        'course_subject_id' => $subject->id,
                        'sort_order' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('batch_subjects');
    }
};
