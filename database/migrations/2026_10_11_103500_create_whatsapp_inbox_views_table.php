<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_inbox_views', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20);
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('meta_whatsapp_message_id')->nullable();
            $table->timestamp('seen_at');
            $table->timestamps();

            $table->unique(
                ['phone', 'user_id', 'meta_whatsapp_message_id'],
                'wa_inbox_views_phone_user_message_unique',
            );
            $table->index(['phone', 'meta_whatsapp_message_id'], 'wa_inbox_views_phone_message_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_inbox_views');
    }
};
