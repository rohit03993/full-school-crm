<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_whatsapp_messages', function (Blueprint $table) {
            $table->string('conversation_category', 48)->nullable()->after('message_type');
            $table->string('message_source', 32)->nullable()->after('conversation_category');
            $table->decimal('estimated_cost_inr', 12, 4)->nullable()->after('message_source');
            $table->foreignId('whatsapp_campaign_recipient_id')
                ->nullable()
                ->after('student_id')
                ->constrained('whatsapp_campaign_recipients')
                ->nullOnDelete();
        });

        Schema::table('whatsapp_campaign_recipients', function (Blueprint $table) {
            $table->string('wamid')->nullable()->after('phone');
            $table->unsignedBigInteger('meta_whatsapp_message_id')->nullable()->after('wamid');
            $table->decimal('estimated_cost_inr', 12, 4)->nullable()->after('message_sent');

            $table->index('meta_whatsapp_message_id');
        });

        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->decimal('estimated_total_cost_inr', 12, 4)->default(0)->after('failed_count');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->dropColumn('estimated_total_cost_inr');
        });

        Schema::table('whatsapp_campaign_recipients', function (Blueprint $table) {
            $table->dropIndex(['meta_whatsapp_message_id']);
            $table->dropColumn(['wamid', 'meta_whatsapp_message_id', 'estimated_cost_inr']);
        });

        Schema::table('meta_whatsapp_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('whatsapp_campaign_recipient_id');
            $table->dropColumn(['conversation_category', 'message_source', 'estimated_cost_inr']);
        });
    }
};
