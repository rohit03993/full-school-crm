<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_case_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('status', 16);
            $table->boolean('title_changed')->default(false);
            $table->boolean('summary_changed')->default(false);
            $table->boolean('closing_note_changed')->default(false);
            $table->boolean('note_changed')->default(false);
            $table->string('old_title')->nullable();
            $table->string('new_title')->nullable();
            $table->text('old_summary')->nullable();
            $table->text('new_summary')->nullable();
            $table->text('old_closing_note')->nullable();
            $table->text('new_closing_note')->nullable();
            $table->foreignId('note_id')->nullable()->constrained('student_case_notes')->nullOnDelete();
            $table->text('old_note')->nullable();
            $table->text('new_note')->nullable();
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['student_case_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_case_revisions');
    }
};
