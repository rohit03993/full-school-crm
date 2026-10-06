<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_recordings', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone_number', 20)->nullable();
            $table->string('call_direction', 20)->default('outgoing');
            $table->string('audio_mime_type')->nullable();
            $table->string('processing_status', 40)->default('UPLOAD_PENDING');
            $table->boolean('handed_off')->default(false);
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->longText('transcript_text')->nullable();
            $table->json('transcript_json')->nullable();
            $table->text('summary')->nullable();
            $table->text('short_summary')->nullable();
            $table->json('ai_analysis_json')->nullable();
            $table->boolean('follow_up_required')->default(false);
            $table->text('follow_up_reason')->nullable();
            $table->date('suggested_follow_up_date')->nullable();
            $table->string('suggested_follow_up_time', 20)->nullable();
            $table->text('processing_error')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_recordings');
    }
};
