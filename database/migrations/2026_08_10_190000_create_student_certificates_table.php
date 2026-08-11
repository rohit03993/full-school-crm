<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_certificates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('enrollment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 32);
            $table->string('serial_number')->unique();
            $table->unsignedInteger('serial')->index();
            $table->date('issued_on');
            $table->foreignId('issued_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->string('pdf_path')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'type']);
            $table->index('issued_on');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_certificates');
    }
};
