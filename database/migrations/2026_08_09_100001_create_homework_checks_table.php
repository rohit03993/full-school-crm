<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_subject_id')->constrained()->cascadeOnDelete();
            $table->string('subject_name');
            $table->text('topic');
            $table->string('status', 20);
            $table->string('parent_mobile', 20)->nullable();
            $table->string('notify_status', 20)->default('not_required');
            $table->timestamp('notified_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['batch_id', 'created_at']);
            $table->index(['student_id', 'created_at']);
            $table->index(['status', 'notify_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_checks');
    }
};
