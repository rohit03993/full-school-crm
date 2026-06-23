<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->timestamp('synced_at')->nullable()->after('is_active');
            $table->json('provider_meta')->nullable()->after('synced_at');
        });

        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->string('audience_type')->default('batch')->after('course_id');
            $table->foreignId('batch_id')->nullable()->after('audience_type')->constrained()->nullOnDelete();
            $table->foreignId('academic_session_id')->nullable()->after('batch_id')->constrained()->nullOnDelete();
            $table->json('campaign_variables')->nullable()->after('visit_status_filter');

            $table->index('audience_type');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_campaigns', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_session_id');
            $table->dropConstrainedForeignId('batch_id');
            $table->dropColumn(['audience_type', 'campaign_variables']);
        });

        Schema::table('whatsapp_templates', function (Blueprint $table) {
            $table->dropColumn(['synced_at', 'provider_meta']);
        });
    }
};
