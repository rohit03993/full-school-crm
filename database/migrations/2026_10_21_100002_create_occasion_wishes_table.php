<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('occasion_wishes', function (Blueprint $table) {
            $table->id();
            $table->string('occasion', 32);
            $table->date('occasion_on');
            $table->string('recipient_key', 64);
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('recipient_name');
            $table->string('mobile', 15)->nullable();
            $table->string('status', 20);
            $table->string('skip_reason')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['occasion', 'occasion_on', 'recipient_key'], 'occasion_wishes_once_per_day');
            $table->index('occasion_on');
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('occasion_wishes');
    }
};
