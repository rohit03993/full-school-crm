<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('face_verification_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('face_request_id', 128)->nullable()->unique();
            $table->foreignId('biometric_punch_id')->nullable()->constrained('biometric_punches')->nullOnDelete();
            $table->foreignId('biometric_device_id')->nullable()->constrained('biometric_devices')->nullOnDelete();
            $table->foreignId('student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('enrollment_number', 64)->index();
            $table->string('face_student_id', 128)->nullable();
            $table->uuid('face_device_id')->nullable();
            $table->string('status', 32)->default('PENDING')->index();
            $table->decimal('score', 8, 6)->nullable();
            $table->dateTime('punched_at')->index();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique('biometric_punch_id', 'face_verification_requests_punch_unique');
            $table->index(['status', 'created_at'], 'face_verification_requests_status_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('face_verification_requests');
    }
};
