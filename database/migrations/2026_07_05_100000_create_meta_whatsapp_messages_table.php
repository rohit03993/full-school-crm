<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('wamid')->nullable()->unique();
            $table->string('direction', 16);
            $table->string('phone', 20);
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->string('template_name')->nullable();
            $table->string('language', 16)->nullable();
            $table->text('body_preview')->nullable();
            $table->string('status', 32)->default('queued');
            $table->string('status_detail')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('status_at')->nullable();
            $table->timestamps();

            $table->index(['direction', 'status']);
            $table->index('phone');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_whatsapp_messages');
    }
};
