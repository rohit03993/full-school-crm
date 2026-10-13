<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_inchat_forms', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('audience', 32);
            $table->string('trigger_word', 32);
            $table->string('button_label', 30)->default('Open form');
            $table->boolean('enabled')->default(true);
            $table->string('status', 32)->default('draft');
            $table->string('flow_id')->nullable();
            $table->string('flow_token')->nullable();
            $table->boolean('needs_republish')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['audience', 'trigger_word']);
        });

        Schema::create('whatsapp_inchat_form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_inchat_form_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('field_type', 32);
            $table->string('label');
            $table->boolean('is_required')->default(false);
            $table->json('choices')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_inchat_form_id', 'sort']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_inchat_form_fields');
        Schema::dropIfExists('whatsapp_inchat_forms');
    }
};
