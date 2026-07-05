<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_whatsapp_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('language', 16)->default('en');
            $table->string('status', 32)->default('APPROVED');
            $table->unsignedTinyInteger('param_count')->default(0);
            $table->json('param_mappings')->nullable();
            $table->text('body')->nullable();
            $table->json('components')->nullable();
            $table->json('provider_meta')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['name', 'language']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_whatsapp_templates');
    }
};
