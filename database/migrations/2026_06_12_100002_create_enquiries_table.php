<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('enquiry_number')->unique();
            $table->foreignId('course_id')->constrained()->restrictOnDelete();
            $table->string('lead_source');
            $table->foreignId('meeting_with_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('meeting_for')->default('school');
            $table->string('visit_type')->default('first_visit');
            $table->text('follow_up_reason')->nullable();
            $table->string('latest_visit_status')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('student_id');
            $table->index('course_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enquiries');
    }
};
