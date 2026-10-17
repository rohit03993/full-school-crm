<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_drops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason');
            $table->foreignId('dropped_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dropped_at');
            $table->timestamps();

            $table->index(['student_id', 'dropped_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_drops');
    }
};
