<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_conduct_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->date('recorded_on');
            $table->json('type_keys');
            $table->boolean('fine_charged')->default(false);
            $table->unsignedInteger('amount')->nullable();
            $table->text('note');
            $table->foreignId('recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['student_id', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_conduct_records');
    }
};
