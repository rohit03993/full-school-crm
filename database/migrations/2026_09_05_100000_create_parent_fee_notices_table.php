<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_fee_notices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->date('due_date');
            $table->foreignId('whatsapp_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('whatsapp_campaign_recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('sent_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('sent_at');
            $table->string('status', 32)->default('queued');
            $table->timestamps();

            $table->index(['student_id', 'sent_at']);
            $table->index('whatsapp_campaign_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_fee_notices');
    }
};
