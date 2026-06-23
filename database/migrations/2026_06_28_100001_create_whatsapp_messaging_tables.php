<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->unsignedTinyInteger('param_count')->default(0);
            $table->json('param_mappings')->nullable();
            $table->text('body')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('name');
        });

        Schema::create('whatsapp_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_template_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->string('visit_status_filter')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('shot_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('shot_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });

        Schema::create('whatsapp_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_call_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20);
            $table->string('status')->default('pending');
            $table->json('template_params')->nullable();
            $table->text('message_sent')->nullable();
            $table->json('provider_response')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_campaign_id', 'status']);
            $table->index('student_id');
        });

        Schema::table('student_calls', function (Blueprint $table) {
            $table->string('whatsapp_auto_status', 20)->nullable()->after('call_direction');
        });
    }

    public function down(): void
    {
        Schema::table('student_calls', function (Blueprint $table) {
            $table->dropColumn('whatsapp_auto_status');
        });

        Schema::dropIfExists('whatsapp_campaign_recipients');
        Schema::dropIfExists('whatsapp_campaigns');
        Schema::dropIfExists('whatsapp_templates');
    }
};
