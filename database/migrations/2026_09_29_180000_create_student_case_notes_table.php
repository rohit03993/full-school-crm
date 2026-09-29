<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_case_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('kind', 16)->default('update');
            $table->text('body');
            $table->timestamps();

            $table->index(['student_case_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_case_notes');
    }
};
