<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_installment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('whatsapp_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index(['student_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_reminder_logs');
    }
};
