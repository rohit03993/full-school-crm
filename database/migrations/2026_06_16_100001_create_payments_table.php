<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fee_structure_id')->constrained()->restrictOnDelete();
            $table->foreignId('student_id')->constrained()->restrictOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 12, 2);
            $table->string('payment_mode');
            $table->string('voucher_number')->nullable();
            $table->string('transaction_id')->nullable();
            $table->string('utr_number')->nullable();
            $table->string('proof_image_path');
            $table->string('receipt_number')->unique();
            $table->string('receipt_path')->nullable();
            $table->foreignId('added_by_user_id')->constrained('users')->restrictOnDelete();
            $table->text('correction_reason')->nullable();
            $table->foreignId('corrected_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('corrected_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'payment_date']);
            $table->index('fee_structure_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
