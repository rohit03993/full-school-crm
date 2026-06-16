<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enquiry_id')->constrained()->restrictOnDelete();
            $table->string('admission_number')->unique();
            $table->string('tenth_board')->nullable();
            $table->decimal('tenth_percentage', 5, 2)->nullable();
            $table->string('twelfth_board')->nullable();
            $table->decimal('twelfth_percentage', 5, 2)->nullable();
            $table->string('graduation')->nullable();
            $table->decimal('graduation_percentage', 5, 2)->nullable();
            $table->string('status')->default('submitted');
            $table->text('staff_remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['student_id', 'status']);
            $table->index('enquiry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admissions');
    }
};
