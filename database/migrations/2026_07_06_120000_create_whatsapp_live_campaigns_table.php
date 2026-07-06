<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_live_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->foreignId('meta_whatsapp_template_id')
                ->constrained('meta_whatsapp_templates')
                ->cascadeOnDelete();
            $table->string('status', 20)->default('draft');
            $table->string('description')->nullable();
            $table->json('default_variables')->nullable();
            $table->timestamp('went_live_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_live_campaigns');
    }
};
