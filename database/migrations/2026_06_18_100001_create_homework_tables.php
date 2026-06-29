<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('homework_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('content_type', 16)->default('text');
            $table->string('file_path')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('whatsapp_sent_count')->default(0);
            $table->unsignedInteger('whatsapp_failed_count')->default(0);
            $table->timestamps();

            $table->index(['batch_id', 'published_at']);
        });

        Schema::create('homework_views', function (Blueprint $table) {
            $table->id();
            $table->foreignId('homework_assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->timestamp('viewed_at');
            $table->timestamps();

            $table->unique(['homework_assignment_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('homework_views');
        Schema::dropIfExists('homework_assignments');
    }
};
