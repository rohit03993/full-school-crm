<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parent_message_sends', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 32);
            $table->boolean('is_resend')->default(false);
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->date('homework_date')->nullable();
            $table->string('test_key')->nullable();
            $table->string('label');
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('parent_count')->default(0);
            $table->foreignId('whatsapp_campaign_id')->nullable()->constrained('whatsapp_campaigns')->nullOnDelete();
            $table->timestamp('sent_at');
            $table->timestamps();

            $table->index('sent_at');
            $table->index(['kind', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parent_message_sends');
    }
};
