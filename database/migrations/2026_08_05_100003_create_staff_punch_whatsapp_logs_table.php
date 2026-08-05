<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_punch_whatsapp_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('employee_code', 64);
            $table->string('state', 8);
            $table->date('punch_date');
            $table->time('punch_time');
            $table->string('phone', 20);
            $table->string('status', 32)->default('queued');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['employee_code', 'punch_date', 'punch_time', 'state'], 'staff_punch_wa_dedupe_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_punch_whatsapp_logs');
    }
};
