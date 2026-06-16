<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enquiry_id')->nullable()->constrained()->nullOnDelete();
            $table->date('visit_date');
            $table->foreignId('staff_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('discussion_summary');
            $table->text('remarks')->nullable();
            $table->date('next_follow_up_date')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->index(['student_id', 'visit_date']);
            $table->index('enquiry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visits');
    }
};
